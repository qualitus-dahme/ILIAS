<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Class ilCalendarUtil
 * @author        Helmut Schottmüller <helmut.schottmueller@mac.com>
 */
class ilCalendarUtil
{
    private static ?ilDateTime $today = null;
    private static array $default_calendar = array();
    public static string $init_done;
    protected static bool $init_datetimepicker = false;

    public static function convertDateToUtcDBTimestamp(\ilDateTime $date = null): ?string
    {
        if (is_null($date)) {
            return null;
        }
        if ($date instanceof \ilDate) {
            return $date->get(IL_CAL_DATE);
        }
        return $date->get(IL_CAL_DATETIME, '', ilTimeZone::UTC);
    }

    public static function _isToday(ilDateTime $date): bool
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        if (!is_object(self::$today)) {
            self::$today = new ilDateTime(time(), IL_CAL_UNIX, $ilUser->getTimeZone());
        }
        return ilDateTime::_equals(self::$today, $date, IL_CAL_DAY, $ilUser->getTimeZone());
    }

    /**
     * numeric month to string
     * @param int month (1-12)
     * @param bool short or long month translation
     */
    public static function _numericMonthToString(int $a_month, bool $a_long = true): string
    {
        global $DIC;

        $lng = $DIC['lng'];
        $month = $a_month < 10 ? '0' . $a_month : $a_month;
        return $a_long ? $lng->txt('month_' . $month . '_long') : $lng->txt('month_' . $month . '_short');
    }

    /**
     * @param int day of week (0 for sunday, 1 for monday)
     * @param bool short or long day translation
     */
    public static function _numericDayToString(int $a_day, bool $a_long = true): string
    {
        global $DIC;

        $lng = $DIC['lng'];
        $lng->loadLanguageModule('dateplaner');
        static $days = array('Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su');

        return $a_long ? $lng->txt($days[$a_day] . '_long') : $lng->txt($days[$a_day] . '_short');
    }

    /**
     * build week day list
     * @access public
     * @param ilDate a day in a week
     * @param int weekstart
     * @return ilDateList
     */
    public static function _buildWeekDayList(ilDate $a_day, int $a_weekstart): ilDateList
    {
        $day_list = new ilDateList(ilDateList::TYPE_DATE);

        $start = clone $a_day;
        $start_info = $start->get(IL_CAL_FKT_GETDATE, '', 'UTC');
        $day_diff = $a_weekstart - $start_info['isoday'];
        if (abs($day_diff) === 7) {
            $day_diff = 0;
        }
        $start->increment(IL_CAL_DAY, $day_diff);
        $day_list->add($start);
        for ($i = 1; $i < 7; $i++) {
            $start->increment(IL_CAL_DAY, 1);
            $day_list->add($start);
        }
        return $day_list;
    }

    /**
     * Build a month day list
     * @param int month
     * @param int year
     * @param int weekstart (0 => Sunday,1 => Monday)
     * @return ilDateList
     */
    public static function _buildMonthDayList(int $a_month, int $a_year, int $weekstart): ilDateList
    {
        $day_list = new ilDateList(ilDateList::TYPE_DATE);

        $prev_month = ($a_month == 1) ? 12 : $a_month - 1;
        $prev_year = ($prev_month == 12) ? $a_year - 1 : $a_year;
        $next_month = $a_month == 12 ? 1 : $a_month + 1;
        $next_year = $a_month == 12 ? $a_year + 1 : $a_year;

        $days_in_month = self::_getMaxDayOfMonth($a_year, $a_month);
        $days_in_prev_month = self::_getMaxDayOfMonth($a_year, $prev_month);

        $week_day['year'] = $a_year;
        $week_day['mon'] = $a_month;
        $week_day['mday'] = 1;
        $week_day['hours'] = 0;
        $week_day['minutes'] = 0;
        $week_day = new ilDate($week_day, IL_CAL_FKT_GETDATE);

        $weekday = $week_day->get(IL_CAL_FKT_DATE, 'w');
        $first_day_offset = (($weekday - $weekstart) < 0) ? 6 : $weekday - $weekstart;

        for ($i = 0; $i < 42; $i++) {
            if ($i < $first_day_offset) {
                $day = $days_in_prev_month - $first_day_offset + $i + 1;

                $day_list->add(new ilDate(
                    gmmktime(
                        0,
                        0,
                        0,
                        $prev_month,
                        $days_in_prev_month - $first_day_offset + $i + 1,
                        $prev_year
                    ),
                    IL_CAL_UNIX
                ));
            } elseif ($i < $days_in_month + $first_day_offset) {
                $day = $i - $first_day_offset + 1;

                $day_list->add(new ilDate(
                    gmmktime(
                        0,
                        0,
                        0,
                        $a_month,
                        $i - $first_day_offset + 1,
                        $a_year
                    ),
                    IL_CAL_UNIX
                ));
            } else {
                $day = $i - $days_in_month - $first_day_offset + 1;
                $day_list->add(new ilDate(
                    gmmktime(
                        0,
                        0,
                        0,
                        $next_month,
                        $i - $days_in_month - $first_day_offset + 1,
                        $next_year
                    ),
                    IL_CAL_UNIX
                ));
            }
            if ($i == 34 and ($day < 15 or $day == $days_in_month)) {
                break;
            }
        }
        return $day_list;
    }

    public static function initJSCalendar(): void
    {
        global $DIC;

        $tpl = $DIC['tpl'];
        $lng = $DIC['lng'];

        if (self::$init_done == "done") {
            return;
        }

        $lng->loadLanguageModule("jscalendar");
        $tpl->addBlockFile(
            "CALENDAR_LANG_JAVASCRIPT",
            "calendar_javascript",
            "tpl.calendar.html",
            "Services/Calendar"
        );
        $tpl->setCurrentBlock("calendar_javascript");
        $tpl->setVariable("FULL_SUNDAY", $lng->txt("l_su"));
        $tpl->setVariable("FULL_MONDAY", $lng->txt("l_mo"));
        $tpl->setVariable("FULL_TUESDAY", $lng->txt("l_tu"));
        $tpl->setVariable("FULL_WEDNESDAY", $lng->txt("l_we"));
        $tpl->setVariable("FULL_THURSDAY", $lng->txt("l_th"));
        $tpl->setVariable("FULL_FRIDAY", $lng->txt("l_fr"));
        $tpl->setVariable("FULL_SATURDAY", $lng->txt("l_sa"));
        $tpl->setVariable("SHORT_SUNDAY", $lng->txt("s_su"));
        $tpl->setVariable("SHORT_MONDAY", $lng->txt("s_mo"));
        $tpl->setVariable("SHORT_TUESDAY", $lng->txt("s_tu"));
        $tpl->setVariable("SHORT_WEDNESDAY", $lng->txt("s_we"));
        $tpl->setVariable("SHORT_THURSDAY", $lng->txt("s_th"));
        $tpl->setVariable("SHORT_FRIDAY", $lng->txt("s_fr"));
        $tpl->setVariable("SHORT_SATURDAY", $lng->txt("s_sa"));
        $tpl->setVariable("FULL_JANUARY", $lng->txt("l_01"));
        $tpl->setVariable("FULL_FEBRUARY", $lng->txt("l_02"));
        $tpl->setVariable("FULL_MARCH", $lng->txt("l_03"));
        $tpl->setVariable("FULL_APRIL", $lng->txt("l_04"));
        $tpl->setVariable("FULL_MAY", $lng->txt("l_05"));
        $tpl->setVariable("FULL_JUNE", $lng->txt("l_06"));
        $tpl->setVariable("FULL_JULY", $lng->txt("l_07"));
        $tpl->setVariable("FULL_AUGUST", $lng->txt("l_08"));
        $tpl->setVariable("FULL_SEPTEMBER", $lng->txt("l_09"));
        $tpl->setVariable("FULL_OCTOBER", $lng->txt("l_10"));
        $tpl->setVariable("FULL_NOVEMBER", $lng->txt("l_11"));
        $tpl->setVariable("FULL_DECEMBER", $lng->txt("l_12"));
        $tpl->setVariable("SHORT_JANUARY", $lng->txt("s_01"));
        $tpl->setVariable("SHORT_FEBRUARY", $lng->txt("s_02"));
        $tpl->setVariable("SHORT_MARCH", $lng->txt("s_03"));
        $tpl->setVariable("SHORT_APRIL", $lng->txt("s_04"));
        $tpl->setVariable("SHORT_MAY", $lng->txt("s_05"));
        $tpl->setVariable("SHORT_JUNE", $lng->txt("s_06"));
        $tpl->setVariable("SHORT_JULY", $lng->txt("s_07"));
        $tpl->setVariable("SHORT_AUGUST", $lng->txt("s_08"));
        $tpl->setVariable("SHORT_SEPTEMBER", $lng->txt("s_09"));
        $tpl->setVariable("SHORT_OCTOBER", $lng->txt("s_10"));
        $tpl->setVariable("SHORT_NOVEMBER", $lng->txt("s_11"));
        $tpl->setVariable("SHORT_DECEMBER", $lng->txt("s_12"));
        $tpl->setVariable("ABOUT_CALENDAR", $lng->txt("about_calendar"));
        $tpl->setVariable("ABOUT_CALENDAR_LONG", $lng->txt("about_calendar_long"));
        $tpl->setVariable("ABOUT_TIME_LONG", $lng->txt("about_time"));
        $tpl->setVariable("PREV_YEAR", $lng->txt("prev_year"));
        $tpl->setVariable("PREV_MONTH", $lng->txt("prev_month"));
        $tpl->setVariable("GO_TODAY", $lng->txt("go_today"));
        $tpl->setVariable("NEXT_MONTH", $lng->txt("next_month"));
        $tpl->setVariable("NEXT_YEAR", $lng->txt("next_year"));
        $tpl->setVariable("SEL_DATE", $lng->txt("select_date"));
        $tpl->setVariable("DRAG_TO_MOVE", $lng->txt("drag_to_move"));
        $tpl->setVariable("PART_TODAY", $lng->txt("part_today"));
        $tpl->setVariable("DAY_FIRST", $lng->txt("day_first"));
        $tpl->setVariable("CLOSE", $lng->txt("close"));
        $tpl->setVariable("TODAY", $lng->txt("today"));
        $tpl->setVariable("TIME_PART", $lng->txt("time_part"));
        $tpl->setVariable("DEF_DATE_FORMAT", $lng->txt("def_date_format"));
        $tpl->setVariable("TT_DATE_FORMAT", $lng->txt("tt_date_format"));
        $tpl->setVariable("WK", $lng->txt("wk"));
        $tpl->setVariable("TIME", $lng->txt("time"));
        $tpl->parseCurrentBlock();
        $tpl->setCurrentBlock("CalendarJS");
        $tpl->setVariable("LOCATION_JAVASCRIPT_CALENDAR", "./Services/Calendar/js/calendar.js");
        $tpl->setVariable("LOCATION_JAVASCRIPT_CALENDAR_SETUP", "./Services/Calendar/js/calendar-setup.js");
        $tpl->parseCurrentBlock();

        self::$init_done = "done";
    }

    public static function getZoneInfoFile($a_tz): string
    {
        if (!array_key_exists($a_tz, self::_getShortTimeZoneList())) {
            return '';
        }
        $timezone_filename = str_replace('/', '_', $a_tz);
        $timezone_filename .= '.ics';
        return './Services/Calendar/zoneinfo/' . $timezone_filename;
    }

    /**
     * get short timezone list
     */
    public static function _getShortTimeZoneList(): array
    {
        return array(
            'Pacific/Samoa' => 'GMT-11: Midway Islands, Samoa',
            'US/Hawaii' => 'GMT-10:00: Hawaii, Polynesia',
            'US/Alaska' => 'GMT-9:00: Alaska',
            'America/Los_Angeles' => 'GMT-8:00: Tijuana, Los Angeles, Seattle, Vancouver',
            'US/Arizona' => 'GMT-7:00: Arizona',
            'America/Chihuahua' => 'GMT-7:00: Chihuahua, La Paz, Mazatlan',
            'America/Denver' => 'GMT-7:00: Arizona, Denver, Salt Lake City, Calgary',
            'America/Chicago' => 'GMT-6:00: Chicago, Dallas, Kansas City, Winnipeg',
            'America/Monterrey' => 'GMT-6:00: Guadalajara, Mexico City, Monterrey',
            'Canada/Saskatchewan' => 'GMT-6:00: Saskatchewan',
            'US/Central' => 'GMT-6:00: Central America',
            'America/Bogota' => 'GMT-5:00: Bogota, Lima, Quito',
            'US/East-Indiana' => 'GMT-5:00: East-Indiana',
            'America/New_York' => 'GMT-5:00: New York, Miami, Atlanta, Detroit, Toronto',
            'Canada/Atlantic' => 'GMT-4:00: Atlantic (Canada)',
            'America/La_Paz' => 'GMT-4:00: Carcas, La Paz',
            'America/Santiago' => 'GMT-4:00: Santiago',
            'Canada/Newfoundland' => 'GMT-3:00: Newfoundland',
            'Brazil/East' => 'GMT-3:00: Sao Paulo',
            'America/Argentina/Buenos_Aires' => 'GMT-3:00: Buenes Aires, Georgtown',
            'Etc/GMT+3' => 'GMT-3:00: Greenland, Uruguay, Surinam',
            'Atlantic/Cape_Verde' => 'GMT-2:00: Cape Verde, Greenland, South Georgia',
            'Atlantic/Azores' => 'GMT-1:00: Azores',
            'Africa/Casablanca' => 'GMT+0:00: Casablanca, Monrovia',
            'Europe/London' => 'GMT+0:00: Dublin, Edinburgh, Lisbon, London',
            'Europe/Berlin' => 'GMT+1:00: Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna',
            'Europe/Belgrade' => 'GMT+1:00: Belgrade, Bratislava, Budapest, Ljubljana, Prague',
            'Europe/Paris' => 'GMT+1:00: Brussels, Copenhagen, Paris, Madrid',
            'Europe/Sarajevo' => 'GMT+1:00: Sarajevo, Skopje, Warsaw, Zagreb',
            'Africa/Lagos' => 'GMT+1:00: West-Central Africa',
            'Europe/Athens' => 'GMT+2:00: Athens, Beirut, Istanbul, Minsk',
            'Europe/Bucharest' => 'GMT+2:00: Bucharest',
            'Africa/Harare' => 'GMT+2:00: Harare, Pratoria',
            'Europe/Helsinki' => 'GMT+2:00: Helsinki, Kiev, Riga, Sofia, Tallinn, Vilnius',
            'Asia/Jerusalem' => 'GMT+2:00: Jerusalem',
            'Africa/Cairo' => 'GMT+2:00: Cairo',
            'Asia/Baghdad' => 'GMT+3:00: Baghdad',
            'Asia/Kuwait' => 'GMT+3:00: Kuwait, Riyadh',
            'Europe/Moscow' => 'GMT+3:00: Moscow, Saint Petersburg',
            'Africa/Nairobi' => 'GMT+3:00: Nairobi',
            'Asia/Tehran' => 'GMT+3:30: Tehran',
            'Asia/Muscat' => 'GMT+4:00: Abu Dhabi, Muscat',
            'Asia/Baku' => 'GMT+4:00: Baku, Tbilisi, Erivan',
            'Asia/Kabul' => 'GMT+4:00: Kabul',
            'Asia/Karachi' => 'GMT+5:00: Islamabad, Karachi, Taschkent',
            'Asia/Yekaterinburg' => 'GMT+5:00: Yekaterinburg',
            'Asia/Calcutta' => 'GMT+5:30: New Dehli',
            'Asia/Katmandu' => 'GMT+5:45: Katmandu',
            'Asia/Novosibirsk' => 'GMT+6:00: Almaty, Novosibirsk',
            'Asia/Dhaka' => 'GMT+6:00: Astana, Dhaka',
            'Asia/Rangoon' => 'GMT+6:00: Sri Jayawardenepura, Rangoon',
            'Asia/Jakarta' => 'GMT+7:00: Bangkok, Hanoi, Jakarta',
            'Asia/Krasnoyarsk' => 'GMT+7:00: Krasnoyarsk',
            'Asia/Irkutsk' => 'GMT+8:00: Irkutsk, Ulan Bator',
            'Asia/Singapore' => 'GMT+8:00: Kuala Lumpour, Singapore',
            'Asia/Hong_Kong' => 'GMT+8:00: Beijing, Chongqing, Hong kong, Urumchi',
            'Australia/Perth' => 'GMT+8:00: Perth',
            'Asia/Taipei' => 'GMT+8:00: Taipei',
            'Asia/Yakutsk' => 'GMT+9:00: Yakutsk',
            'Asia/Tokyo' => 'GMT+9:00: Osaka, Sapporo, Tokyo',
            'Asia/Seoul' => 'GMT+9:00: Seoul, Darwin, Adelaide',
            'Australia/Brisbane' => 'GMT+10:00: Brisbane',
            'Australia/Sydney' => 'GMT+10:00: Canberra, Melbourne, Sydney',
            'Pacific/Guam' => 'GMT+10:00: Guam, Port Moresby',
            'Australia/Hobart' => 'GMT+10:00: Hobart',
            'Asia/Vladivostok' => 'GMT+10:00: Vladivostok',
            'Asia/Magadan' => 'GMT+11:00: Salomon Islands, New Caledonia, Magadan',
            'Pacific/Auckland' => 'GMT+12:00: Auckland, Wellington',
            'Pacific/Fiji' => 'GMT+12:00: Fiji, Kamchatka, Marshall-Islands'
        );
    }

    /**
     * check if a given year is a leap year
     */
    public static function _isLeapYear(int $a_year): bool
    {
        $is_leap = false;
        if ($a_year % 4 == 0) {
            $is_leap = true;
            if ($a_year % 100 == 0) {
                $is_leap = false;
                if ($a_year % 400) {
                    return true;
                }
            }
        }
        return $is_leap;
    }

    /**
     * get max day of month
     * 2008,2 => 29
     */
    public static function _getMaxDayOfMonth(int $a_year, int $a_month): int
    {
        if (function_exists('cal_days_in_month')) {
            return cal_days_in_month(CAL_GREGORIAN, $a_month, $a_year);
        }
        return (int) date('t', mktime(0, 0, 0, $a_month, 1, $a_year));
    }

    /**
     * Calculate best font color from html hex color code
     * @param string hex value of color
     * @return string #ffffff or #000000
     */
    public static function calculateFontColor(string $a_html_color_code): string
    {
        if (strpos($a_html_color_code, '#') !== 0 or strlen($a_html_color_code) != 7) {
            return '#000000';
        }

        // http://en.wikipedia.org/wiki/Luminance_(relative)
        $lum = round(hexdec(substr($a_html_color_code, 1, 2)) * 0.2126 +
            hexdec(substr($a_html_color_code, 3, 2)) * 0.7152 +
            hexdec(substr($a_html_color_code, 5, 2)) * 0.0722);
        return ($lum <= 128) ? "#FFFFFF" : "#000000";
    }

    /**
     * Get hour selection depending on user specific hour format.
     */
    public static function getHourSelection(int $a_format): array
    {
        $options = [];
        switch ($a_format) {
            case ilCalendarSettings::TIME_FORMAT_24:
                for ($i = 0; $i < 24; $i++) {
                    $options[$i] = sprintf('%02d:00', $i);
                }
                break;

            case ilCalendarSettings::TIME_FORMAT_12:
                for ($i = 0; $i < 24; $i++) {
                    $options[$i] = date('h a', mktime($i, 0, 0, 1, 1, 2000));
                }
                break;
        }
        return $options;
    }

    /**
     * Init the default calendar for given type and user
     */
    public static function initDefaultCalendarByType(
        int $a_type_id,
        int $a_usr_id,
        string $a_title,
        bool $a_create = false
    ): ?ilCalendarCategory {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        if (isset(self::$default_calendar[$a_usr_id]) and isset(self::$default_calendar[$a_usr_id][$a_type_id])) {
            return self::$default_calendar[$a_usr_id][$a_type_id];
        }

        $query = "SELECT cat_id FROM cal_categories " .
            "WHERE obj_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND type = " . $ilDB->quote($a_type_id, 'integer');
        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            return self::$default_calendar[$a_usr_id][$a_type_id] = new ilCalendarCategory($row->cat_id);
        }

        if (!$a_create) {
            return null;
        }

        // Create default calendar
        self::$default_calendar[$a_usr_id][$a_type_id] = new ilCalendarCategory();
        self::$default_calendar[$a_usr_id][$a_type_id]->setType($a_type_id);
        self::$default_calendar[$a_usr_id][$a_type_id]->setColor(ilCalendarCategory::DEFAULT_COLOR);
        self::$default_calendar[$a_usr_id][$a_type_id]->setTitle($a_title);
        self::$default_calendar[$a_usr_id][$a_type_id]->setObjId($a_usr_id);
        self::$default_calendar[$a_usr_id][$a_type_id]->add();

        return self::$default_calendar[$a_usr_id][$a_type_id];
    }

    /**
     * Parse current user setting into date/time format
     * @param ?int $a_add_time 1=hh:mm, 2=hh:mm:ss
     * @param bool $a_for_parsing
     * @return string
     */
    public static function getUserDateFormat(int $a_add_time = 0, bool $a_for_parsing = false): string
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];

        $format = (string) $ilUser->getDateFormat();

        if ($a_add_time) {
            $format .= " " . (($ilUser->getTimeFormat() == ilCalendarSettings::TIME_FORMAT_24)
                    ? "H:i"
                    : "h:ia");
            if ($a_add_time == 2) {
                $format .= ":s";
            }
        }

        // translate datepicker format to PHP format
        if (!$a_for_parsing) {
            $format = str_replace("d", "DD", $format);
            $format = str_replace("m", "MM", $format);
            $format = str_replace("i", "mm", $format);
            $format = str_replace("Y", "YYYY", $format);
            $format = str_replace("H", "HH", $format);
            $format = str_replace("h", "hh", $format);
            $format = str_replace("s", "ss", $format);
        }

        return $format;
    }

    public static function initDateTimePicker(): void
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();

        if (!self::$init_datetimepicker) {
            $tpl->addJavaScript("./node_modules/moment/min/moment-with-locales.min.js");
            // unminified version does not work with jQuery 3.0
            // https://github.com/Eonasdan/bootstrap-datetimepicker/issues/1684
            $tpl->addJavaScript("./node_modules/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js");
            $tpl->addJavaScript("Services/Form/js/Form.js"); // see ilPropertyFormGUI
            self::$init_datetimepicker = true;
        }
    }

    /**
     * Add date time picker to element
     * @param string $a_id
     * @param int    $a_add_time 1=hh:mm, 2=hh:mm:ss
     * @param array  $a_custom_config
     * @param string $a_id2
     * @param array  $a_custom_config2
     * @param string $a_toggle_id
     * @param string $a_subform_id
     */
    public static function addDateTimePicker(
        string $a_id,
        ?int $a_add_time = null,
        ?array $a_custom_config = null,
        ?string $a_id2 = null,
        ?array $a_custom_config2 = null,
        ?string $a_toggle_id = null,
        ?string $a_subform_id = null
    ): void {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();
        foreach (self::getCodeForPicker(
            $a_id,
            $a_add_time,
            $a_custom_config,
            $a_id2,
            $a_custom_config2,
            $a_toggle_id,
            $a_subform_id
        ) as $code) {
            $tpl->addOnLoadCode($code);
        }
    }

    /**
     * Add date time picker to element
     * @param string $a_id
     * @param int    $a_add_time 1=hh:mm, 2=hh:mm:ss
     * @param array  $a_custom_config
     * @param string $a_id2
     * @param array  $a_custom_config2
     * @param string $a_toggle_id
     * @param string $a_subform_id
     * @return string
     */
    public static function getCodeForPicker(
        string $a_id,
        ?int $a_add_time = null,
        ?array $a_custom_config = null,
        ?string $a_id2 = null,
        ?array $a_custom_config2 = null,
        ?string $a_toggle_id = null,
        ?string $a_subform_id = null
    ): array {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        self::initDateTimePicker();

        // fix for mantis 22994 => default to english language
        $language = 'en';
        if ($ilUser->getLanguage() != 'ar') {
            $language = $ilUser->getLanguage();
        }
        $default = array(
            'locale' => $language,
            'stepping' => 5,
            'useCurrent' => false,
            'calendarWeeks' => true,
            'toolbarPlacement' => 'top',
            //'showTodayButton' => true,
            'showClear' => true,
            //'showClose' => true,
            'keepInvalid' => true,
            'sideBySide' => true,
            //'collapse' => false,
            'format' => self::getUserDateFormat((bool) $a_add_time)
        );

        $config = (!$a_custom_config)
            ? $default
            : array_merge($default, $a_custom_config);

        $code = [];

        /**
         * Whether the start of the week in the picker is Sunday or Monday
         * should depend on the user calendar settings (#21666).
         * Unfortunately this is not a direct config of the picker, but is
         * inherent in the locale, so it needs to be shoehorned into there.
         *
         * 0 for Sunday, 1 for Monday
         */
        $start_of_week = ilCalendarUserSettings::_getInstanceByUserId($ilUser->getId())->getWeekStart();
        $code[] =
            'if (moment) {
                moment.updateLocale("' . $language . '", {week: {dow: ' . $start_of_week . '}});
            }';

        $code[] = '$("#' . $a_id . '").datetimepicker(' . json_encode($config) . ')';

        // optional 2nd picker aka duration
        if ($a_id2) {
            $config2 = (!$a_custom_config2)
                ? $default
                : array_merge($default, $a_custom_config2);

            $config2["useCurrent"] = false; //Important! See issue #1075

            $code[] = '$("#' . $a_id2 . '").datetimepicker(' . json_encode($config2) . ')';

            // duration limits, diff and subform handling
            $code[] = 'il.Form.initDateDurationPicker("' . $a_id . '","' . $a_id2 . '","' . $a_toggle_id . '","' . $a_subform_id . '");';
        } elseif ($a_subform_id) {
            // subform handling
            $code[] = 'il.Form.initDatePicker("' . $a_id . '","' . $a_subform_id . '");';
        }
        return $code;
    }

    /**
     * Parse (incoming) string to date/time object
     * @param string $a_date
     * @param bool   $a_add_time 1=hh:mm, 2=hh:mm:ss
     * @param bool   $a_use_generic_format
     * @return array date, warnings, errors
     * @throws ilDateTimeException
     */
    public static function parseDateString(string $a_date, bool $a_add_time = false, bool $a_use_generic_format = false): array
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        if (!$a_use_generic_format) {
            $out_format = self::getUserDateFormat($a_add_time, true);
        } else {
            $out_format = $a_add_time
                ? "Y-m-d H:i:s"
                : "Y-m-d";
        }
        $tmp = date_parse_from_format($out_format, $a_date);
        $date = null;

        if (!$tmp["error_count"] &&
            !$tmp["warning_count"]) {
            $format = $tmp["year"] . "-" .
                str_pad($tmp["month"], 2, "0", STR_PAD_LEFT) . "-" .
                str_pad($tmp["day"], 2, "0", STR_PAD_LEFT);

            if ($a_add_time) {
                $format .= " " .
                    str_pad($tmp["hour"], 2, "0", STR_PAD_LEFT) . ":" .
                    str_pad($tmp["minute"], 2, "0", STR_PAD_LEFT) . ":" .
                    str_pad($tmp["second"], 2, "0", STR_PAD_LEFT);

                $date = new ilDateTime($format, IL_CAL_DATETIME, $ilUser->getTimeZone());
            } else {
                $date = new ilDate($format, IL_CAL_DATE);
            }
        }

        return array(
            "date" => $date
            ,
            "warnings" => sizeof($tmp["warnings"])
                ? $tmp["warnings"]
                : null
            ,
            "errors" => sizeof($tmp["errors"])
                ? $tmp["errors"]
                : null
        );
    }

    /**
     * Try to parse incoming value to date object
     * @param string|ilDateTime $a_value
     * @param int   $a_add_time
     * @return ilDateTime|ilDate
     */
    public static function parseIncomingDate($a_value, bool $a_add_time = false): ?ilDateTime
    {
        // already datetime object?
        if ($a_value instanceof ilDateTime) {
            return $a_value;
        } elseif (trim($a_value)) {
            // try user-specific format
            $parsed = self::parseDateString($a_value, $a_add_time);
            if (is_object($parsed["date"])) {
                return $parsed["date"];
            } else {
                // try generic format
                $parsed = self::parseDateString($a_value, $a_add_time, true);
                if (is_object($parsed["date"])) {
                    return $parsed["date"];
                }
            }
        }
        return null;
    }
}
