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

use ILIAS\File\Capabilities\Capabilities;
use ILIAS\File\Capabilities\Permissions;

/**
 * Access class for file objects.
 * @author  Alex Killing <alex.killing@gmx.de>
 * @author  Stefan Born <stefan.born@phzh.ch>
 * @version $Id$
 * @ingroup ModulesFile
 */
class ilObjFileAccess extends ilObjectAccess implements ilWACCheckingClass
{
    /**
     * Contains an array of extensions separated by space.
     * Since this array is needed for every file object displayed on a
     * repository page, we only create it once, and cache it here.
     */
    protected static array $inline_file_extensions = [];

    protected static array $preload_list_gui_data = [];

    protected function checkAccessToObjectId(int $obj_id): bool
    {
        global $DIC;
        $ilAccess = $DIC['ilAccess'];
        /**
         * @var $ilAccess ilAccessHandler
         */
        foreach (ilObject::_getAllReferences($obj_id) as $ref_id) {
            if ($ilAccess->checkAccess('read', '', $ref_id)) {
                return true;
            }
        }

        return false;
    }

    public function canBeDelivered(ilWACPath $ilWACPath): bool
    {
        return false;
    }

    /**
     * get commands
     * this method returns an array of all possible commands/permission combinations
     * example:
     * $commands = array
     *    (
     *        array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
     *        array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
     *    );
     * @return array<int, mixed[]>
     */
    public static function _getCommands(): array
    {
        return [
            [
                "permission" => Permissions::READ->value,
                "cmd" => Capabilities::DOWNLOAD->value,
                "lang_var" => "download",
                // "default" => true, // we decide the best matching capability later in ListGUI
            ],
            [
                "permission" => Permissions::VISIBLE->value,
                "cmd" => Capabilities::INFO_PAGE->value,
                "lang_var" => "info",
            ],
            [
                "permission" => Permissions::VISIBLE->value,
                "cmd" => Capabilities::FORCED_INFO_PAGE->value,
                "lang_var" => "info",
            ],
            [
                "permission" => Permissions::WRITE->value,
                "cmd" => Capabilities::UNZIP->value,
                "lang_var" => "unzip",
            ],
            [
                "permission" => Permissions::EDIT_CONTENT->value,
                "cmd" => Capabilities::EDIT_EXTERNAL->value,
                "lang_var" => "open_external_editor",
            ],
            [
                "permission" => Permissions::VIEW_CONTENT->value,
                "cmd" => Capabilities::VIEW_EXTERNAL->value,
                "lang_var" => "open_external_viewer",
            ],
            [
                "permission" => Permissions::WRITE->value,
                "cmd" => Capabilities::MANAGE_VERSIONS->value,
                "lang_var" => "versions",
            ],
            [
                "permission" => Permissions::WRITE->value,
                "cmd" => Capabilities::EDIT_SETTINGS->value,
                "lang_var" => "settings"
            ]
        ];
    }

    /**
     * checks whether a user may invoke a command or not
     * (this method is called by ilAccessHandler::checkAccess)
     */
    public function _checkAccess(string $cmd, string $permission, int $ref_id, int $obj_id, ?int $user_id = null): bool
    {
        global $DIC;
        $ilUser = $DIC['ilUser'];
        $lng = $DIC['lng'];
        $rbacsystem = $DIC['rbacsystem'];
        $ilAccess = $DIC['ilAccess'];
        if (is_null($user_id)) {
            $user_id = $ilUser->getId();
        }

        switch ($cmd) {
            case "view":
                if (!self::_lookupOnline($obj_id)
                    && !$rbacsystem->checkAccessOfUser($user_id, 'write', $ref_id)
                ) {
                    $ilAccess->addInfoItem(ilAccessInfo::IL_NO_OBJECT_ACCESS, $lng->txt("offline"));

                    return false;
                }
                break;
                // for permission query feature
            case Capabilities::INFO_PAGE->value:
                if (!self::_lookupOnline($obj_id)) {
                    $ilAccess->addInfoItem(ilAccessInfo::IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                } else {
                    $ilAccess->addInfoItem(ilAccessInfo::IL_STATUS_MESSAGE, $lng->txt("online"));
                }
                break;
        }
        switch ($permission) {
            case "read":
            case "visible":
                if (!self::_lookupOnline($obj_id)
                    && (!$rbacsystem->checkAccessOfUser($user_id, 'write', $ref_id))
                ) {
                    $ilAccess->addInfoItem(ilAccessInfo::IL_NO_OBJECT_ACCESS, $lng->txt("offline"));

                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * check whether goto script will succeed
     */
    public static function _checkGoto(string $a_target): bool
    {
        global $DIC;
        $ilAccess = $DIC['ilAccess'];

        $t_arr = explode("_", $a_target);

        // personal workspace context: do not force normal login
        if (isset($t_arr[2]) && $t_arr[2] === "wsp") {
            return ilSharedResourceGUI::hasAccess($t_arr[1]);
        }
        if ($t_arr[0] !== "file") {
            return false;
        }
        if (((int) $t_arr[1]) <= 0) {
            return false;
        }
        if ($ilAccess->checkAccess("visible", "", $t_arr[1])) {
            return true;
        }
        return (bool) $ilAccess->checkAccess("read", "", $t_arr[1]);
    }

    public static function _shouldDownloadDirectly(int $obj_id): bool
    {
        global $DIC;

        $result = $DIC->database()->fetchAssoc(
            $DIC->database()->queryF(
                "SELECT on_click_mode FROM file_data WHERE file_id = %s;",
                ['integer'],
                [$obj_id]
            )
        );

        if (empty($result)) {
            return false;
        }

        return (((int) $result['on_click_mode']) === ilObjFile::CLICK_MODE_DOWNLOAD);
    }

    /**
     * @deprecated
     */
    public static function _lookupFileSize(int $a_id, bool $by_reference = true): int
    {
        try {
            $info_repo = new ilObjFileInfoRepository();
            $info = $by_reference ? $info_repo->getByRefId($a_id) : $info_repo->getByObjectId($a_id);

            return (int) $info->getFileSize()->inBytes();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Gets the file extension of the specified file name.
     * The file name extension is converted to lower case before it is returned.
     * For example, for the file name "HELLO.MP3", this function returns "mp3".
     * A file name extension can have multiple parts. For the file name
     * "hello.tar.gz", this function returns "gz".
     */
    public static function _getFileExtension(string $a_file_name): string
    {
        if (preg_match('/\.([a-z0-9]+)\z/i', $a_file_name, $matches) == 1) {
            return strtolower($matches[1]);
        }
        return '';
    }

    /**
     * Returns true, if a file with the specified name, is usually hidden from
     * the user.
     * - Filenames starting with '.' are hidden Unix files
     * - Filenames ending with '~' are temporary Unix files
     * - Filenames starting with '~$' are temporary Windows files
     * - The file "Thumbs.db" is a hidden Windows file
     */
    public static function _isFileHidden(string $a_file_name): bool
    {
        return str_starts_with($a_file_name, '.') || str_ends_with($a_file_name, '~')
            || str_starts_with($a_file_name, '~$')
            || $a_file_name === 'Thumbs.db';
    }

    /**
     * Appends the text " - Copy" to a filename in the language of
     * the current user.
     * If the provided $nth_copy parameter is greater than 1, then
     * is appended in round brackets. If $nth_copy parameter is null, then
     * the function determines the copy number on its own.
     * If this function detects, that the filename already ends with " - Copy",
     * or with "- Copy ($nth_copy), it only appends the number of the copy to
     * the filename.
     * This function retains the extension of the filename.
     * Examples:
     * - Calling ilObjFileAccess::_appendCopyToTitle('Hello.txt', 1)
     *   returns: "Hello - Copy.txt".
     * - Calling ilObjFileAccess::_appendCopyToTitle('Hello.txt', 2)
     *   returns: "Hello - Copy (2).txt".
     * - Calling ilObjFileAccess::_appendCopyToTitle('Hello - Copy (3).txt', 2)
     *   returns: "Hello - Copy (2).txt".
     * - Calling ilObjFileAccess::_appendCopyToTitle('Hello - Copy (3).txt', null)
     *   returns: "Hello - Copy (4).txt".
     */
    public static function _appendNumberOfCopyToFilename($a_file_name, $nth_copy = null): string
    {
        global $DIC;
        $lng = $DIC['lng'];

        $filenameWithoutExtension = $a_file_name;

        $extension = null;

        // create a regular expression from the language text copy_n_of_suffix, so that
        // we can match it against $filenameWithoutExtension, and retrieve the number of the copy.
        // for example, if copy_n_of_suffix is 'Copy (%1s)', this creates the regular
        // expression '/ Copy \\([0-9]+)\\)$/'.
        $nthCopyRegex = preg_replace(
            '/([\^$.\[\]|()?*+{}])/',
            '\\\\${1}',
            ' '
            . $lng->txt('copy_n_of_suffix')
        );
        $nthCopyRegex = '/' . preg_replace('/%1\\\\\$s/', '(\d+)', $nthCopyRegex) . '$/';

        // Get the filename without any previously added number of copy.
        // Determine the number of copy, if it has not been specified.
        if (preg_match($nthCopyRegex, (string) $filenameWithoutExtension, $matches)) {
            // this is going to be at least the third copy of the filename
            $filenameWithoutCopy = substr((string) $filenameWithoutExtension, 0, -strlen($matches[0]));
            if ($nth_copy == null) {
                $nth_copy = $matches[1] + 1;
            }
        } elseif (str_ends_with((string) $filenameWithoutExtension, ' ' . $lng->txt('copy_of_suffix'))) {
            // this is going to be the second copy of the filename
            $filenameWithoutCopy = substr(
                (string) $filenameWithoutExtension,
                0,
                -strlen(
                    ' '
                    . $lng->txt('copy_of_suffix')
                )
            );
            if ($nth_copy == null) {
                $nth_copy = 2;
            }
        } else {
            // this is going to be the first copy of the filename
            $filenameWithoutCopy = $filenameWithoutExtension;
            if ($nth_copy == null) {
                $nth_copy = 1;
            }
        }

        // Construct the new filename
        if ($nth_copy > 1) {
            // this is at least the second copy of the filename, append " - Copy ($nth_copy)"
            return $filenameWithoutCopy . sprintf(
                ' '
                    . $lng->txt('copy_n_of_suffix'),
                $nth_copy
            )
                . $extension;
        }

        // this is the first copy of the filename, append " - Copy"
        return $filenameWithoutCopy . ' ' . $lng->txt('copy_of_suffix') . $extension;
    }

    /**
     * Gets the permanent download link for the file.
     */
    public static function _getPermanentDownloadLink(int $ref_id): string
    {
        return ilLink::_getStaticLink($ref_id, "file", true, "download");
    }

    /**
     * @param int[] $obj_ids
     * @param int[] $ref_ids
     */
    public static function _preloadData(array $obj_ids, array $ref_ids): void
    {
        $info = new ilObjFileInfoRepository();
        $info->preloadData($obj_ids);
    }

    public static function _lookupOnline(int $a_obj_id): bool
    {
        $file_obj = new ilObjFile($a_obj_id, false);
        return $file_obj->getObjectProperties()->getPropertyIsOnline()->getIsOnline();
    }
}
