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
 * ilAppointmentPresentationUserGUI class presents modal information for personal appointments.
 * @author            Jesús López Reyes <lopez@leifos.com>
 * @version           $Id$
 * @ilCtrl_IsCalledBy ilAppointmentPresentationUserGUI: ilCalendarAppointmentPresentationGUI
 * @ingroup           ServicesCalendar
 */
class ilAppointmentPresentationUserGUI extends ilAppointmentPresentationGUI implements ilCalendarAppointmentPresentation
{
    public function collectPropertiesAndActions(): void
    {
        global $DIC;

        $a_app = $this->appointment;
        $f = $DIC->ui()->factory();
        $r = $DIC->ui()->renderer();
        $cat_info = $this->getCatInfo();

        // event title
        $this->addInfoSection($a_app["event"]->getPresentationTitle());

        // event description
        $this->addEventDescription($a_app);

        // calendar info
        if ($cat_info != null) {
            $this->addCalendarInfo($cat_info);
        }

        // owner
        $this->addInfoProperty($this->lng->txt("cal_owner"), $this->getUserName($cat_info['obj_id']));
        $this->addListItemProperty($this->lng->txt("cal_owner"), $this->getUserName($cat_info['obj_id']));

        $this->addInfoSection($this->lng->txt("cal_usr_info"));

        // event location
        $this->addEventLocation($a_app);

        //user notifications
        $notification = new ilCalendarUserNotification($a_app['event']->getEntryId());
        $recipients = $notification->getRecipients();
        if (count($recipients) > 0) {
            $str_notification = "";
            foreach ($recipients as $rcp) {
                switch ($rcp['type']) {
                    case ilCalendarUserNotification::TYPE_USER:
                        $str_notification .= $this->getUserName($rcp['usr_id']) . "<br>";
                        break;
                    case ilCalendarUserNotification::TYPE_EMAIL:
                        $str_notification .= $rcp['email'] . "<br>";
                        break;
                }
            }
            $this->addInfoProperty($this->lng->txt("cal_user_notification"), $str_notification);
        }
    }
}
