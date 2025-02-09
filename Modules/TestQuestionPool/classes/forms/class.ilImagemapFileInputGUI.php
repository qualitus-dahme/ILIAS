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

use ILIAS\UI\Renderer;
use ILIAS\UI\Component\Symbol\Glyph\Factory as GlyphFactory;

/**
* This class represents an image map file property in a property form.
*
* @author Helmut Schottmüller <ilias@aurealis.de>
* @version $Id$
* @ingroup	ServicesForm
*/
class ilImagemapFileInputGUI extends ilImageFileInputGUI
{
    protected $areas = array();
    protected $image_path = "";
    protected $image_path_web = "";
    protected $line_color = "";

    protected $pointsUncheckedFieldEnabled = false;

    protected GlyphFactory $glyph_factory;
    protected Renderer $renderer;

    /**
    * Constructor
    *
    * @param	string	$a_title	Title
    * @param	string	$a_postvar	Post Variable
    */
    public function __construct($a_title = "", $a_postvar = "")
    {
        parent::__construct($a_title, $a_postvar);

        global $DIC;
        $this->glyph_factory = $DIC->ui()->factory()->symbol()->glyph();
        $this->renderer = $DIC->ui()->renderer();
    }

    public function setPointsUncheckedFieldEnabled($pointsUncheckedFieldEnabled): void
    {
        $this->pointsUncheckedFieldEnabled = (bool) $pointsUncheckedFieldEnabled;
    }

    public function getPointsUncheckedFieldEnabled(): bool
    {
        return $this->pointsUncheckedFieldEnabled;
    }

    public function setAreas($a_areas): void
    {
        $this->areas = $a_areas;
    }

    public function getLineColor(): string
    {
        return $this->line_color;
    }

    public function setLineColor($a_color): void
    {
        $this->line_color = $a_color;
    }

    public function getImagePath(): string
    {
        return $this->image_path;
    }

    public function setImagePath($a_path): void
    {
        $this->image_path = $a_path;
    }

    public function getImagePathWeb(): string
    {
        return $this->image_path_web;
    }

    public function setImagePathWeb($a_path_web): void
    {
        $this->image_path_web = $a_path_web;
    }

    public function setAreasByArray($a_areas): void
    {
        if (is_array($a_areas['name'])) {
            $this->areas = array();
            foreach ($a_areas['name'] as $idx => $name) {
                if ($this->getPointsUncheckedFieldEnabled() && isset($a_areas['points_unchecked'])) {
                    $pointsUnchecked = $a_areas['points_unchecked'][$idx];
                } else {
                    $pointsUnchecked = 0.0;
                }

                array_push($this->areas, new ASS_AnswerImagemap(
                    $name,
                    $a_areas['points'][$idx],
                    $idx,
                    $a_areas['coords'][$idx],
                    $a_areas['shape'][$idx],
                    -1,
                    $pointsUnchecked
                ));

                $imagemap = new ASS_AnswerImagemap($name, $a_areas['points'][$idx], $idx, 0, -1);
                $imagemap->setCoords($a_areas['coords'][$idx]);
                $imagemap->setArea($a_areas['shape'][$idx]);
                $imagemap->setPointsUnchecked($pointsUnchecked);
                array_push($this->areas, $imagemap);
            }
        }
    }

    public function getAreas(): array
    {
        return $this->areas;
    }

    /**
    * Set value by array
    *
    * @param	array	$a_values	value array
    */
    public function setValueByArray(array $a_values): void
    {
        if (isset($a_value[$this->getPostVar() . '_name'])) {
            $this->setValue($a_values[$this->getPostVar() . '_name']);
        }
        if (isset($a_value[$this->getPostVar()]['coords'])) {
            $this->setAreasByArray($a_values[$this->getPostVar()]['coords']);
        }
    }

    public function setValue($a_value): void
    {
        parent::setValue($a_value);
    }

    public function getInput(): array
    {
        return parent::getInput();
    }

    private function getPostBody(): array
    {
        $val = $this->arrayArray($this->getPostVar());
        $val = ilArrayUtil::stripSlashesRecursive($val);
        return $val;
    }

    /**
    * Check input, strip slashes etc. set alert, if input is not ok.
    * @return	boolean		Input ok, true/false
    */
    public function checkInput(): bool
    {
        $lng = $this->lng;

        // remove trailing '/'
        $_FILES[$this->getPostVar()]["name"] = rtrim($_FILES[$this->getPostVar()]["name"], '/');

        $filename = $_FILES[$this->getPostVar()]["name"];
        $filename_arr = pathinfo($_FILES[$this->getPostVar()]["name"]);
        $suffix = $filename_arr["extension"] ?? '';
        $mimetype = $_FILES[$this->getPostVar()]["type"];
        $size_bytes = $_FILES[$this->getPostVar()]["size"];
        $temp_name = $_FILES[$this->getPostVar()]["tmp_name"];
        $error = $_FILES[$this->getPostVar()]["error"];

        // error handling
        if ($error > 0) {
            switch ($error) {
                case UPLOAD_ERR_FORM_SIZE:
                case UPLOAD_ERR_INI_SIZE:
                    $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
                    return false;
                    break;

                case UPLOAD_ERR_PARTIAL:
                    $this->setAlert($lng->txt("form_msg_file_partially_uploaded"));
                    return false;
                    break;

                case UPLOAD_ERR_NO_FILE:
                    if ($this->getRequired()) {
                        if (!strlen($this->getValue())) {
                            $this->setAlert($lng->txt("form_msg_file_no_upload"));
                            return false;
                        }
                    }
                    break;

                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->setAlert($lng->txt("form_msg_file_missing_tmp_dir"));
                    return false;
                    break;

                case UPLOAD_ERR_CANT_WRITE:
                    $this->setAlert($lng->txt("form_msg_file_cannot_write_to_disk"));
                    return false;
                    break;

                case UPLOAD_ERR_EXTENSION:
                    $this->setAlert($lng->txt("form_msg_file_upload_stopped_ext"));
                    return false;
                    break;
            }
        }

        // check suffixes
        if ($_FILES[$this->getPostVar()]["tmp_name"] != "" &&
            is_array($this->getSuffixes())) {
            if (!in_array(strtolower($suffix), $this->getSuffixes())) {
                $this->setAlert($lng->txt("form_msg_file_wrong_file_type"));
                return false;
            }
        }

        // virus handling
        if ($_FILES[$this->getPostVar()]["tmp_name"] != "") {
            $vir = ilVirusScanner::virusHandling($temp_name, $filename);
            if ($vir[0] == false) {
                $this->setAlert($lng->txt("form_msg_file_virus_found") . "<br />" . $vir[1]);
                return false;
            }
        }

        $post_body = $this->getPostBody();

        $max = 0;
        if (isset($post_body['coords']) && is_array($post_body['coords']['name'])) {
            foreach ($post_body['coords']['name'] as $idx => $name) {
                if ($this->getRequired() && (
                    !isset($post_body['coords']['points'][$idx]) ||
                    $post_body['coords']['points'][$idx] == ''
                )) {
                    $this->setAlert($lng->txt('form_msg_area_missing_points'));
                    return false;
                }

                if ((!is_numeric($post_body['coords']['points'][$idx]))) {
                    $this->setAlert($lng->txt('form_msg_numeric_value_required'));
                    return false;
                }

                if ($post_body['coords']['points'][$idx] > 0) {
                    $max = $post_body['coords']['points'][$idx];
                }
            }
        }

        if ($max == 0 && (!$filename) && !$_FILES['imagemapfile']['tmp_name']) {
            $this->setAlert($lng->txt("enter_enough_positive_points"));
            return false;
        }
        return true;
    }

    /**
    * Insert property html
    */
    public function insert(ilTemplate $a_tpl): void
    {
        $lng = $this->lng;

        $template = new ilTemplate("tpl.prop_imagemap_file.html", true, true, "Modules/TestQuestionPool");

        $this->outputSuffixes($template, "allowed_image_suffixes");

        if ($this->getImage() != "") {
            if (strlen($this->getValue())) {
                $template->setCurrentBlock("has_value");
                $template->setVariable("TEXT_IMAGE_NAME", $this->getValue());
                $template->setVariable("POST_VAR_D", $this->getPostVar());
                $template->parseCurrentBlock();
            }
            $template->setCurrentBlock("image");
            if (count($this->getAreas())) {
                $preview = new ilImagemapPreview($this->getImagePath() . $this->getValue());
                foreach ($this->getAreas() as $index => $area) {
                    $preview->addArea($index, $area->getArea(), $area->getCoords(), $area->getAnswertext(), "", "", true, $this->getLineColor());
                }
                $preview->createPreview();
                $imagepath = $this->getImagePathWeb() . $preview->getPreviewFilename($this->getImagePath(), $this->getValue()) . "?img=" . time();
                $template->setVariable("SRC_IMAGE", $imagepath);
            } else {
                $template->setVariable("SRC_IMAGE", $this->getImage());
            }
            $template->setVariable("ALT_IMAGE", $this->getAlt());
            $template->setVariable("POST_VAR_D", $this->getPostVar());
            $template->setVariable(
                "TXT_DELETE_EXISTING",
                $lng->txt("delete_existing_file")
            );
            $template->setVariable("TEXT_ADD_RECT", $lng->txt('add_rect'));
            $template->setVariable("TEXT_ADD_CIRCLE", $lng->txt('add_circle'));
            $template->setVariable("TEXT_ADD_POLY", $lng->txt('add_poly'));
            $template->parseCurrentBlock();
        }

        if (is_array($this->getAreas()) && $this->getAreas()) {
            $counter = 0;
            foreach ($this->getAreas() as $area) {
                if (strlen($area->getPoints())) {
                    $template->setCurrentBlock('area_points_value');
                    $template->setVariable('VALUE_POINTS', $area->getPoints());
                    $template->parseCurrentBlock();
                }
                if ($this->getPointsUncheckedFieldEnabled()) {
                    if (strlen($area->getPointsUnchecked())) {
                        $template->setCurrentBlock('area_points_unchecked_value');
                        $template->setVariable('VALUE_POINTS_UNCHECKED', $area->getPointsUnchecked());
                        $template->parseCurrentBlock();
                    }

                    $template->setCurrentBlock('area_points_unchecked_field');
                    $template->parseCurrentBlock();
                }
                if (strlen($area->getAnswertext())) {
                    $template->setCurrentBlock('area_name_value');
                    $template->setVariable('VALUE_NAME', htmlspecialchars($area->getAnswertext()));
                    $template->parseCurrentBlock();
                }
                $template->setCurrentBlock('row');
                $template->setVariable('POST_VAR_R', $this->getPostVar());
                $template->setVariable('TEXT_SHAPE', strtoupper($area->getArea()));
                $template->setVariable('VALUE_SHAPE', $area->getArea());
                $coords = preg_replace("/(\d+,\d+,)/", "\$1 ", $area->getCoords());
                $template->setVariable('VALUE_COORDINATES', $area->getCoords());
                $template->setVariable('TEXT_COORDINATES', $coords);
                $template->setVariable("REMOVE_BUTTON", $this->renderer->render(
                    $this->glyph_factory->remove()->withAction('#')
                ));
                $template->parseCurrentBlock();
                $counter++;
            }
            $template->setCurrentBlock("areas");
            $template->setVariable("TEXT_NAME", $lng->txt("ass_imap_hint"));
            if ($this->getPointsUncheckedFieldEnabled()) {
                $template->setVariable("TEXT_POINTS", $lng->txt("points_checked"));

                $template->setCurrentBlock('area_points_unchecked_head');
                $template->setVariable("TEXT_POINTS_UNCHECKED", $lng->txt("points_unchecked"));
                $template->parseCurrentBlock();
            } else {
                $template->setVariable("TEXT_POINTS", $lng->txt("points"));
            }
            $template->setVariable("TEXT_SHAPE", $lng->txt("shape"));
            $template->setVariable("TEXT_COORDINATES", $lng->txt("coordinates"));
            $template->setVariable("TEXT_COMMANDS", $lng->txt("actions"));
            $template->parseCurrentBlock();
        }

        $template->setVariable("POST_VAR", $this->getPostVar());
        $template->setVariable("ID", $this->getFieldId());
        $template->setVariable("TXT_BROWSE", $lng->txt("select_file"));
        $template->setVariable('MAX_SIZE_WARNING', $this->lng->txt('form_msg_file_size_exceeds'));
        $template->setVariable('MAX_SIZE', $this->upload_limit->getPhpUploadLimitInBytes());
        $template->setVariable("TXT_MAX_SIZE", $lng->txt("file_notice") . " " .
        $this->getMaxFileSizeString());

        $a_tpl->setCurrentBlock("prop_generic");
        $a_tpl->setVariable("PROP_GENERIC", $template->get());
        $a_tpl->parseCurrentBlock();

        $this->tpl->addJavascript("./Modules/TestQuestionPool/templates/default/answerwizardinput.js");
        $this->tpl->addJavascript("./Modules/TestQuestionPool/templates/default/imagemap.js");
    }
}
