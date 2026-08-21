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
class ilBookingManagerAppEventListener
{
    /**
     * Handle an event in a listener.
     *
     * @param\tstring\t$a_component\tcomponent, e.g. "Modules/Forum" or "Services/User"
     * @param\tstring\t$a_event\t\tevent e.g. "createUser", "updateUser", "deleteUser", ...
     * @param\tarray\t$a_parameter\tparameter array (assoc), array("name" => ..., "phone_office" => ...)
     */
    public static function handleEvent(
        string $a_component,
        string $a_event,
        array $a_parameter
    ): void {
        global $DIC;
        switch ($a_component) {
            case "Services/User":
                switch ($a_event) {
                    case "deleteUser":
                        $DIC->bookingManager()->internal()->domain()->userEvent()->handleDeletion((int) $a_parameter["usr_id"]);
                        break;
                }
                break;
            case "components/ILIAS/ILIASObject":
                switch ($a_event) {
                    case "toTrash":
                        $DIC->bookingManager()->internal()->domain()->objectEvent()->handleDeletion([(int) $a_parameter["ref_id"]]);
                        break;
                    case "beforeDeletion":
                        $object = $a_parameter["object"] ?? null;
                        if (
                            $object instanceof ilObjBookingPool
                            && $object->getRefId() > 0
                            && !ilObject::_isInTrash($object->getRefId())
                        ) {
                            $DIC->bookingManager()->internal()->domain()->objectEvent()->handleDeletion([$object->getRefId()]);
                        }
                        break;
                }
                break;
        }
    }
}
