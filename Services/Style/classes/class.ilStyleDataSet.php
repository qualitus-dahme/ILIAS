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

use ILIAS\Style\Content\Access;
use ILIAS\Style\Content;

/**
 * Style Data set class
 *
 * This class implements the following entities:
 * - sty: table object_data
 * - sty_setting: table style_setting
 * - sty_char: table style classes
 * - sty_char_title: table style class titles
 * - sty_parameter: table style_parameter
 * - sty_color: table style colors
 * - sty_template: table style_template
 * - sty_template_class: table style_template_class
 * - sty_media_query: table sty_media_query
 * - sty_usage: table style_usage
 *
 * - object_style: this is a special entity which allows to export using the ID of the consuming object (e.g. wiki)
 *                 the "sty" entity will be detemined and exported afterwards (if a non global style has been assigned)
 *
 * @author Alex Killing <alex.killing@gmx.de>
 */
class ilStyleDataSet extends ilDataSet
{
    protected ?ilObjStyleSheet $current_obj = null;
    protected Content\InternalRepoService $repo;
    /**
     * @var ilLogger
     */
    protected $log;

    /**
     * @var ilRbacSystem
     */
    protected $rbacsystem;

    /**
     * @var \ilObjUser
     */
    protected $user;

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        parent::__construct();
        $this->log = ilLoggerFactory::getLogger('styl');
        $this->log->debug("constructed");
        $this->rbacsystem = $DIC->rbac()->system();
        $this->user = $DIC->user();
        $this->repo = $DIC->contentStyle()->internal()->repo();
    }


    /**
     * Get supported versions
     * @return array version
     */
    public function getSupportedVersions(): array
    {
        return array("5.1.0", "8.0");
    }

    /**
     * Get xml namespace
     * @param
     * @return string
     */
    public function getXmlNamespace(string $a_entity, string $a_schema_version): string
    {
        return "http://www.ilias.de/xml/Services/Style/" . $a_entity;
    }

    /**
     * Get field types for entity
     * @param string $a_entity  entity
     * @param string $a_version version number
     * @return array types array
     */
    protected function getTypes(string $a_entity, string $a_version): array
    {
        if ($a_entity == "sty") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "Id" => "integer",
                        "Title" => "text",
                        "Description" => "text",
                        "ImagesDir" => "directory"
                    );
            }
        }

        if ($a_entity == "object_style") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "ObjectId" => "integer"
                    );
            }
        }

        if ($a_entity == "sty_setting") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "StyleId" => "integer",
                        "Name" => "test",
                        "Value" => "text"
                    );
            }
        }

        if ($a_entity == "sty_char") {
            switch ($a_version) {
                case "5.1.0":
                    return array(
                        "StyleId" => "integer",
                        "Type" => "text",
                        "Characteristic" => "text",
                        "Hide" => "integer"
                    );
                case "8.0":
                    return array(
                        "StyleId" => "integer",
                        "Type" => "text",
                        "Characteristic" => "text",
                        "Hide" => "integer",
                        "OrderNr" => "integer",
                        "Outdate" => "integer"
                    );
            }
        }

        if ($a_entity == "sty_char_title") {
            switch ($a_version) {
                case "8.0":
                    return array(
                        "StyleId" => "integer",
                        "Type" => "text",
                        "Characteristic" => "text",
                        "Lang" => "text",
                        "Title" => "text"
                    );
            }
        }

        if ($a_entity == "sty_parameter") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "StyleId" => "integer",
                        "Tag" => "text",
                        "Class" => "text",
                        "Parameter" => "text",
                        "Value" => "text",
                        "Type" => "text",
                        "MqId" => "integer",
                        "Custom" => "integer"
                    );
            }
        }

        if ($a_entity == "sty_color") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "StyleId" => "integer",
                        "ColorName" => "text",
                        "ColorCode" => "text"
                    );
            }
        }

        if ($a_entity == "sty_media_query") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "Id" => "integer",
                        "StyleId" => "integer",
                        "OrderNr" => "integer",
                        "MQuery" => "text"
                    );
            }
        }

        if ($a_entity == "sty_template") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "Id" => "integer",
                        "StyleId" => "integer",
                        "Name" => "text",
                        "Preview" => "text",
                        "TempType" => "text"
                    );
            }
        }

        if ($a_entity == "sty_template_class") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "TemplateId" => "integer",
                        "ClassType" => "text",
                        "Class" => "text"
                    );
            }
        }

        if ($a_entity == "sty_usage") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    return array(
                        "ObjId" => "integer",
                        "StyleId" => "integer"
                    );
            }
        }
    }

    /**
     * Get xml record
     * @param
     * @return array
     */
    public function getXmlRecord(string $a_entity, string $a_version, array $a_set): array
    {
        if ($a_entity == "sty") {
            $dir = ilObjStyleSheet::_getImagesDirectory($a_set["Id"]);
            $a_set["ImagesDir"] = $dir;
        }

        return $a_set;
    }

    /**
     * Read data
     * @param
     * @return void
     */
    public function readData(string $a_entity, string $a_version, array $a_ids): void
    {
        $ilDB = $this->db;

        if (!is_array($a_ids)) {
            $a_ids = array($a_ids);
        }

        if ($a_entity == "object_style") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    foreach ($a_ids as $id) {
                        $this->data[] = array("ObjectId" => $id);
                    }
                    break;
            }
        }

        if ($a_entity == "sty") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT o.title, o.description, o.obj_id id" .
                        " FROM object_data o " .
                        " WHERE " . $ilDB->in("o.obj_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_setting") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT style_id, name, value" .
                        " FROM style_setting " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_char") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT style_id, type, characteristic, hide, order_nr, outdated" .
                        " FROM style_char " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_char_title") {
            switch ($a_version) {
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT style_id, type, characteristic, lang, title" .
                        " FROM style_char_title " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_parameter") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT style_id, tag, class, parameter, value, type, mq_id, custom" .
                        " FROM style_parameter " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_color") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT style_id, color_name, color_code" .
                        " FROM style_color " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_media_query") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT id, style_id, order_nr, mquery m_query" .
                        " FROM sty_media_query " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_template") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT id, style_id, name, preview, temp_type" .
                        " FROM style_template " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_template_class") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT template_id, class_type, class" .
                        " FROM style_template_class " .
                        " WHERE " . $ilDB->in("template_id", $a_ids, false, "integer"));
                    break;
            }
        }

        if ($a_entity == "sty_usage") {
            switch ($a_version) {
                case "5.1.0":
                case "8.0":
                    $this->getDirectDataFromQuery("SELECT obj_id, style_id" .
                        " FROM style_usage " .
                        " WHERE " . $ilDB->in("style_id", $a_ids, false, "integer"));
                    break;
            }
        }
    }

    /**
     * Determine the dependent sets of data
     */
    protected function getDependencies(
        string $a_entity,
        string $a_version,
        ?array $a_rec = null,
        ?array $a_ids = null
    ): array {
        $this->ds_log->debug("entity: " . $a_entity . ", rec: " . print_r($a_rec, true));
        switch ($a_entity) {
            case "object_style":
                $this->ds_log->debug("object id: " . ($a_rec["ObjectId"] ?? null));
                $style_id = ilObjStyleSheet::lookupObjectStyle($a_rec["ObjectId"] ?? 0);
                $this->ds_log->debug("style id: " . $style_id);
                //if ($style_id > 0 && !ilObjStyleSheet::_lookupStandard($style_id))
                if ($style_id > 0 && ilObject::_lookupType($style_id) == "sty") {			// #0019337 always export style, if valid
                    return array(
                        "sty" => array("ids" => $style_id));
                }
                return array();
                break;

            case "sty":
                return array(
                    "sty_setting" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_media_query" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_char" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_char_title" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_color" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_parameter" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_template" => array("ids" => $a_rec["Id"] ?? null),
                    "sty_usage" => array("ids" => $a_rec["Id"] ?? null)
                );

            case "sty_template":
                return array(
                    "sty_template_class" => array("ids" => $a_rec["Id"] ?? null)
                );
        }

        return [];
    }


    /**
     * Import record
     * @param
     * @return void
     */
    public function importRecord(string $a_entity, array $a_types, array $a_rec, ilImportMapping $a_mapping, string $a_schema_version): void
    {
        global $DIC;
        $service = $DIC->contentStyle()->internal();
        $access_manager = $service->domain()->access(
            0,
            $this->user->getId()
        );
        $access_manager->enableWrite(true);

        $style_id = (isset($this->current_obj))
            ? $this->current_obj->getId()
            : 0;
        $characteristic_manager = $service->domain()->characteristic(
            $style_id,
            $access_manager
        );

        $color_manager = $service->domain()->color(
            $style_id,
            $access_manager
        );

        $a_rec = $this->stripTags($a_rec);
        switch ($a_entity) {
            case "sty":
                $this->log->debug("Entity: " . $a_entity);
                if ($new_id = $a_mapping->getMapping('Services/Container', 'objs', $a_rec['Id'])) {
                    $newObj = ilObjectFactory::getInstanceByObjId($new_id, false);
                } else {
                    $newObj = new ilObjStyleSheet();
                    $newObj->create(0, true);
                }

                $newObj->setTitle($a_rec["Title"]);
                $newObj->setDescription($a_rec["Description"]);
                $newObj->update(true);

                $this->current_obj = $newObj;
                $a_mapping->addMapping("Services/Style", "sty", $a_rec["Id"], $newObj->getId());
                $a_mapping->addMapping("Services/Object", "obj", $a_rec["Id"], $newObj->getId());
                $this->log->debug("Added mapping Services/Style sty  " . $a_rec["Id"] . " > " . $newObj->getId());

                $dir = str_replace("..", "", $a_rec["ImagesDir"]);
                if ($dir != "" && $this->getImportDirectory() != "") {
                    $source_dir = $this->getImportDirectory() . "/" . $dir;
                    $target_dir = $dir = ilObjStyleSheet::_getImagesDirectory($newObj->getId());
                    ilFileUtils::rCopy($source_dir, $target_dir);
                }
                break;

            case "sty_setting":
                $this->current_obj->writeStyleSetting($a_rec["Name"], $a_rec["Value"]);
                break;

            case "sty_char":
                $this->current_obj->addCharacteristic($a_rec["Type"], $a_rec["Characteristic"], $a_rec["Hide"], (int) ($a_rec["OrderNr"] ?? 0), (bool) ($a_rec["Outdated"] ?? false));
                break;

            case "sty_char_title":
                $char_repo = $this->repo->characteristic();
                $char_repo->addTitle(
                    $this->current_obj->getId(),
                    $a_rec["Type"],
                    $a_rec["Characteristic"],
                    $a_rec["Lang"],
                    $a_rec["Title"],
                );
                break;

            case "sty_parameter":
                $mq_id = (int) $a_mapping->getMapping("Services/Style", "media_query", $a_rec["MqId"]);
                $characteristic_manager->replaceParameter($a_rec["Tag"], $a_rec["Class"], $a_rec["Parameter"], $a_rec["Value"], $a_rec["Type"], $mq_id, $a_rec["Custom"]);
                break;

            case "sty_color":
                $color_manager->addColor($a_rec["ColorName"], $a_rec["ColorCode"]);
                break;

            case "sty_media_query":
                $mq_id = $this->current_obj->addMediaQuery($a_rec["MQuery"], $a_rec["OrderNr"]);
                $a_mapping->addMapping("Services/Style", "media_query", $a_rec["Id"], $mq_id);
                break;

            case "sty_template":
                $tid = $this->current_obj->addTemplate($a_rec["TempType"], $a_rec["Name"], array());
                $a_mapping->addMapping("Services/Style", "template", $a_rec["Id"], $tid);
                break;

            case "sty_template_class":
                $tid = (int) $a_mapping->getMapping("Services/Style", "template", $a_rec["TemplateId"]);
                $this->current_obj->addTemplateClass($tid, $a_rec["ClassType"], $a_rec["Class"]);
                break;

            case "sty_usage":
                $obj_id = (int) $a_mapping->getMapping("Services/Object", "obj", $a_rec["ObjId"]);
                $style_id = (int) $a_mapping->getMapping("Services/Style", "sty", $a_rec["StyleId"]);
                if ($obj_id > 0 && $style_id > 0) {
                    ilObjStyleSheet::writeStyleUsage($obj_id, $style_id);
                    ilObjStyleSheet::writeOwner($obj_id, $style_id);
                }
                break;
        }
    }
}
