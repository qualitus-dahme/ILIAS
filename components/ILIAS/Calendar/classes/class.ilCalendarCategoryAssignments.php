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

declare(strict_types=1);
/**
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 * @ingroup ServicesCalendar
 */
class ilCalendarCategoryAssignments
{
    protected ilDBInterface $db;

    protected int $cal_entry_id = 0;
    protected array $assignments = [];

    public function __construct(int $a_cal_entry_id)
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->cal_entry_id = $a_cal_entry_id;
        $this->read();
    }

    public static function _lookupCategories(int $a_cal_id): array
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = "SELECT cat_id FROM cal_cat_assignments " .
            "WHERE cal_id = " . $ilDB->quote($a_cal_id, 'integer') . " ";
        $res = $ilDB->query($query);
        $cat_ids = [];
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $cat_ids[] = (int) $row->cat_id;
        }
        return $cat_ids;
    }

    public static function _lookupCategory(int $a_cal_id): int
    {
        if (count($cats = self::_lookupCategories($a_cal_id))) {
            return $cats[0];
        }
        return 0;
    }

    /**
     * @param int[] $a_cal_ids
     * @return array<int, int>
     */
    public static function _getAppointmentCalendars(array $a_cal_ids): array
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = "SELECT * FROM cal_cat_assignments " .
            "WHERE " . $ilDB->in('cal_id', $a_cal_ids, false, 'integer');
        $res = $ilDB->query($query);
        $map = [];
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $map[(int) $row->cal_id] = (int) $row->cat_id;
        }
        return $map;
    }

    /**
     * Get assigned apointments
     * @param int[]
     * @return int[]
     */
    public static function _getAssignedAppointments(array $a_cat_id): array
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = "SELECT * FROM cal_cat_assignments " .
            "WHERE " . $ilDB->in('cat_id', $a_cat_id, false, 'integer');

        $res = $ilDB->query($query);
        $cal_ids = [];
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $cal_ids[] = (int) $row->cal_id;
        }
        return $cal_ids;
    }

    /**
     * @param int[] $a_cat_ids
     * @return int
     */
    public static function lookupNumberOfAssignedAppointments(array $a_cat_ids): int
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = 'SELECT COUNT(*) num FROM cal_cat_assignments ' .
            'WHERE ' . $ilDB->in('cat_id', $a_cat_ids, false, 'integer');
        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            return (int) $row->num;
        }
        return 0;
    }

    /**
     * get automatic generated appointments of category
     * @return int[]
     */
    public static function _getAutoGeneratedAppointmentsByObjId(int $a_obj_id): array
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = "SELECT ce.cal_id FROM cal_categories cc " .
            "JOIN cal_cat_assignments cca ON cc.cat_id = cca.cat_id " .
            "JOIN cal_entries ce ON cca.cal_id = ce.cal_id " .
            "WHERE auto_generated = 1 " .
            "AND obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " ";
        $res = $ilDB->query($query);
        $apps = [];
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $apps[] = (int) $row->cal_id;
        }
        return $apps;
    }

    /**
     * Delete appointment assignment
     */
    public static function _deleteByAppointmentId(int $a_app_id): void
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = "DELETE FROM cal_cat_assignments " .
            "WHERE cal_id = " . $ilDB->quote($a_app_id, 'integer') . " ";
        $res = $ilDB->manipulate($query);
    }

    /**
     * Delete assignments by category id
     * @access public
     * @param int category_id
     * @return
     * @static
     */
    public static function _deleteByCategoryId(int $a_cat_id): void
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = "DELETE FROM cal_cat_assignments " .
            "WHERE cat_id = " . $ilDB->quote($a_cat_id, 'integer') . " ";
        $res = $ilDB->manipulate($query);
    }

    /**
     * get first assignment
     */
    public function getFirstAssignment(): ?int
    {
        return $this->assignments[0] ?? null;
    }

    /**
     * @return int[]
     */
    public function getAssignments(): array
    {
        return $this->assignments;
    }

    public function addAssignment(int $a_cal_cat_id): void
    {
        $query = "INSERT INTO cal_cat_assignments (cal_id,cat_id) " .
            "VALUES ( " .
            $this->db->quote($this->cal_entry_id, 'integer') . ", " .
            $this->db->quote($a_cal_cat_id, 'integer') . " " .
            ")";
        $res = $this->db->manipulate($query);
        $this->assignments[] = $a_cal_cat_id;
    }

    public function deleteAssignment(int $a_cat_id): void
    {
        $query = "DELETE FROM cal_cat_assignments " .
            "WHERE cal_id = " . $this->db->quote($this->cal_entry_id, 'integer') . ", " .
            "AND cat_id = " . $this->db->quote($a_cat_id, 'integer') . " ";
        $res = $this->db->manipulate($query);

        if (($key = array_search($a_cat_id, $this->assignments)) !== false) {
            unset($this->assignments[$key]);
        }
    }

    public function deleteAssignments(): void
    {
        $query = "DELETE FROM cal_cat_assignments " .
            "WHERE cal_id = " . $this->db->quote($this->cal_entry_id, 'integer') . " ";
        $res = $this->db->manipulate($query);
    }

    private function read()
    {
        $query = "SELECT * FROM cal_cat_assignments " .
            "WHERE cal_id = " . $this->db->quote($this->cal_entry_id, 'integer') . " ";

        $res = $this->db->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $this->assignments[] = (int) $row->cat_id;
        }
    }
}
