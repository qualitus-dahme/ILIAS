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
* Class for imagemap question exports
*
* assImagemapQuestionExport is a class for imagemap question exports
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id$
* @ingroup ModulesTestQuestionPool
*/
class assImagemapQuestionExport extends assQuestionExport
{
    /**
    * Returns a QTI xml representation of the question
    * Returns a QTI xml representation of the question and sets the internal
    * domxml variable with the DOM XML representation of the QTI xml representation
    * @return string The QTI xml representation of the question
    * @access public
    */
    public function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false): string
    {
        global $DIC;
        $ilias = $DIC['ilias'];

        $a_xml_writer = new ilXmlWriter();
        // set xml header
        $a_xml_writer->xmlHeader();
        $a_xml_writer->xmlStartTag("questestinterop");
        $attrs = [
            "ident" => "il_" . IL_INST_ID . "_qst_" . $this->object->getId(),
            "title" => $this->object->getTitle(),
            "maxattempts" => $this->object->getNrOfTries()
        ];
        $a_xml_writer->xmlStartTag("item", $attrs);
        // add question description
        $a_xml_writer->xmlElement("qticomment", null, $this->object->getComment());
        $a_xml_writer->xmlStartTag("itemmetadata");
        $a_xml_writer->xmlStartTag("qtimetadata");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "ILIAS_VERSION");
        $a_xml_writer->xmlElement("fieldentry", null, $ilias->getSetting("ilias_version"));
        $a_xml_writer->xmlEndTag("qtimetadatafield");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "QUESTIONTYPE");
        $a_xml_writer->xmlElement("fieldentry", null, IMAGEMAP_QUESTION_IDENTIFIER);
        $a_xml_writer->xmlEndTag("qtimetadatafield");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "IS_MULTIPLE_CHOICE");
        $a_xml_writer->xmlElement("fieldentry", null, ($this->object->getIsMultipleChoice()) ? "1" : "0");
        $a_xml_writer->xmlEndTag("qtimetadatafield");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "AUTHOR");
        $a_xml_writer->xmlElement("fieldentry", null, $this->object->getAuthor());
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        // additional content editing information
        $this->addAdditionalContentEditingModeInformation($a_xml_writer);
        $this->addGeneralMetadata($a_xml_writer);

        $a_xml_writer->xmlEndTag("qtimetadata");
        $a_xml_writer->xmlEndTag("itemmetadata");

        // PART I: qti presentation
        $attrs = [
            "label" => $this->object->getTitle()
        ];
        $a_xml_writer->xmlStartTag("presentation", $attrs);
        // add flow to presentation
        $a_xml_writer->xmlStartTag("flow");
        // add material with question text to presentation
        $this->addQTIMaterial($a_xml_writer, $this->object->getQuestion());
        // add answers to presentation
        $attrs = [
            "ident" => "IM",
            "rcardinality" => "Single"
        ];
        $a_xml_writer->xmlStartTag("response_xy", $attrs);
        $a_xml_writer = $this->addSuggestedSolution($a_xml_writer);
        $a_xml_writer->xmlStartTag("render_hotspot");
        $a_xml_writer->xmlStartTag("material");
        $imagetype = "image/jpeg";
        if (preg_match("/.*\.(png|gif)$/i", $this->object->getImageFilename(), $matches)) {
            $imagetype = "image/" . strtolower($matches[1]);
        }
        $attrs = [
            "imagtype" => $imagetype,
            "label" => $this->object->getImageFilename()
        ];
        if ($a_include_binary) {
            if ($force_image_references) {
                $attrs["uri"] = $this->object->getImagePathWeb() . $this->object->getImageFilename();
                $a_xml_writer->xmlElement("matimage", $attrs);
            } else {
                $attrs["embedded"] = "base64";
                $imagepath = $this->object->getImagePath() . $this->object->getImageFilename();
                $fh = fopen($imagepath, "rb");
                if ($fh == false) {
                    global $DIC;
                    $ilErr = $DIC['ilErr'];
                    $ilErr->raiseError($GLOBALS['DIC']['lng']->txt("error_open_image_file"), $ilErr->MESSAGE);
                }
                $imagefile = fread($fh, filesize($imagepath));
                fclose($fh);
                $base64 = base64_encode($imagefile);
                $a_xml_writer->xmlElement("matimage", $attrs, $base64, false, false);
            }
        } else {
            $a_xml_writer->xmlElement("matimage", $attrs);
        }
        $a_xml_writer->xmlEndTag("material");

        // add answers
        foreach ($this->object->getAnswers() as $index => $answer) {
            $rared = "";
            switch ($answer->getArea()) {
                case "rect":
                    $rarea = "Rectangle";
                    break;
                case "circle":
                    $rarea = "Ellipse";
                    break;
                case "poly":
                    $rarea = "Bounded";
                    break;
            }
            $attrs = [
                "ident" => $index,
                "rarea" => $rarea
            ];
            $a_xml_writer->xmlStartTag("response_label", $attrs);
            $a_xml_writer->xmlData($answer->getCoords());
            $a_xml_writer->xmlStartTag("material");
            $a_xml_writer->xmlElement("mattext", null, $answer->getAnswertext());
            $a_xml_writer->xmlEndTag("material");
            $a_xml_writer->xmlEndTag("response_label");
        }
        $a_xml_writer->xmlEndTag("render_hotspot");
        $a_xml_writer->xmlEndTag("response_xy");
        $a_xml_writer->xmlEndTag("flow");
        $a_xml_writer->xmlEndTag("presentation");

        // PART II: qti resprocessing
        $a_xml_writer->xmlStartTag("resprocessing");
        $a_xml_writer->xmlStartTag("outcomes");
        $a_xml_writer->xmlStartTag("decvar");
        $a_xml_writer->xmlEndTag("decvar");
        $a_xml_writer->xmlEndTag("outcomes");
        // add response conditions
        foreach ($this->object->getAnswers() as $index => $answer) {
            $attrs = [
                "continue" => "Yes"
            ];
            $a_xml_writer->xmlStartTag("respcondition", $attrs);
            // qti conditionvar
            $a_xml_writer->xmlStartTag("conditionvar");
            if (!$answer->isStateSet()) {
                $a_xml_writer->xmlStartTag("not");
            }
            $areatype = "";
            switch ($answer->getArea()) {
                case "rect":
                    $areatype = "Rectangle";
                    break;
                case "circle":
                    $areatype = "Ellipse";
                    break;
                case "poly":
                    $areatype = "Bounded";
                    break;
            }
            $attrs = [
                "respident" => "IM",
                "areatype" => $areatype
            ];
            $a_xml_writer->xmlElement("varequal", $attrs, $answer->getCoords());
            if (!$answer->isStateSet()) {
                $a_xml_writer->xmlEndTag("not");
            }
            $a_xml_writer->xmlEndTag("conditionvar");
            // qti setvar
            $attrs = [
                "action" => "Add"
            ];
            $a_xml_writer->xmlElement("setvar", $attrs, $answer->getPoints());
            $linkrefid = "response_$index";
            $attrs = [
                "feedbacktype" => "Response",
                "linkrefid" => $linkrefid
            ];
            $a_xml_writer->xmlElement("displayfeedback", $attrs);
            $a_xml_writer->xmlEndTag("respcondition");
            $attrs = [
                "continue" => "Yes"
            ];
            $a_xml_writer->xmlStartTag("respcondition", $attrs);
            // qti conditionvar
            $a_xml_writer->xmlStartTag("conditionvar");
            $attrs = [
                "respident" => "IM"
            ];
            $a_xml_writer->xmlStartTag("not");
            $a_xml_writer->xmlElement("varequal", $attrs, $answer->getCoords());
            $a_xml_writer->xmlEndTag("not");
            $a_xml_writer->xmlEndTag("conditionvar");
            // qti setvar
            $attrs = [
                "action" => "Add"
            ];
            $a_xml_writer->xmlElement("setvar", $attrs, $answer->getPointsUnchecked());
            $a_xml_writer->xmlEndTag("respcondition");
        }

        $answers = $this->object->getAnswers();
        $feedback_allcorrect = $this->object->feedbackOBJ->getGenericFeedbackExportPresentation(
            $this->object->getId(),
            true
        );
        if (strlen($feedback_allcorrect) && count($answers) > 0) {
            $attrs = [
                "continue" => "Yes"
            ];
            $a_xml_writer->xmlStartTag("respcondition", $attrs);
            // qti conditionvar
            $a_xml_writer->xmlStartTag("conditionvar");
            if (!$this->object->getIsMultipleChoice()) {
                $bestindex = 0;
                $maxpoints = 0;
                foreach ($answers as $index => $answer) {
                    if ($answer->getPoints() > $maxpoints) {
                        $maxpoints = $answer->getPoints();
                        $bestindex = $index;
                    }
                }

                $areatype = "";
                $answer = $answers[$bestindex];
                switch ($answer->getArea()) {
                    case "rect":
                        $areatype = "Rectangle";
                        break;
                    case "circle":
                        $areatype = "Ellipse";
                        break;
                    case "poly":
                        $areatype = "Bounded";
                        break;
                }
                $attrs = [
                    "respident" => "IM",
                    "areatype" => $areatype
                ];
                $a_xml_writer->xmlElement("varinside", $attrs, $answer->getCoords());
            } else {
                foreach ($answers as $index => $answer) {
                    if ($answer->getPoints() < $answer->getPointsUnchecked()) {
                        $a_xml_writer->xmlStartTag("not");
                    }
                    switch ($answer->getArea()) {
                        case "rect":
                            $areatype = "Rectangle";
                            break;
                        case "circle":
                            $areatype = "Ellipse";
                            break;
                        case "poly":
                            $areatype = "Bounded";
                            break;
                    }
                    $attrs = [
                        "respident" => "IM",
                        "areatype" => $areatype
                    ];
                    $a_xml_writer->xmlElement("varequal", $attrs, $index);
                    if ($answer->getPoints() < $answer->getPointsUnchecked()) {
                        $a_xml_writer->xmlEndTag("not");
                    }
                }
            }

            $a_xml_writer->xmlEndTag("conditionvar");
            // qti displayfeedback
            $attrs = [
                "feedbacktype" => "Response",
                "linkrefid" => "response_allcorrect"
            ];
            $a_xml_writer->xmlElement("displayfeedback", $attrs);
            $a_xml_writer->xmlEndTag("respcondition");
        }

        $feedback_onenotcorrect = $this->object->feedbackOBJ->getGenericFeedbackExportPresentation(
            $this->object->getId(),
            false
        );
        if (strlen($feedback_onenotcorrect) && count($answers) > 0) {
            $attrs = [
                "continue" => "Yes"
            ];
            $a_xml_writer->xmlStartTag("respcondition", $attrs);
            // qti conditionvar
            $a_xml_writer->xmlStartTag("conditionvar");
            if (!$this->object->getIsMultipleChoice()) {
                $bestindex = 0;
                $maxpoints = 0;
                foreach ($answers as $index => $answer) {
                    if ($answer->getPoints() > $maxpoints) {
                        $maxpoints = $answer->getPoints();
                        $bestindex = $index;
                    }
                }
                $attrs = [
                    "respident" => "IM"
                ];
                $a_xml_writer->xmlStartTag("not");

                $areatype = "";
                $answer = $answers[$bestindex];
                switch ($answer->getArea()) {
                    case "rect":
                        $areatype = "Rectangle";
                        break;
                    case "circle":
                        $areatype = "Ellipse";
                        break;
                    case "poly":
                        $areatype = "Bounded";
                        break;
                }
                $attrs = [
                    "respident" => "IM",
                    "areatype" => $areatype
                ];
                $a_xml_writer->xmlElement("varinside", $attrs, $answer->getCoords());

                $a_xml_writer->xmlEndTag("not");
            } else {
                foreach ($answers as $index => $answer) {
                    if ($index > 0) {
                        $a_xml_writer->xmlStartTag("or");
                    }
                    if ($answer->getPoints() >= $answer->getPointsUnchecked()) {
                        $a_xml_writer->xmlStartTag("not");
                    }
                    switch ($answer->getArea()) {
                        case "rect":
                            $areatype = "Rectangle";
                            break;
                        case "circle":
                            $areatype = "Ellipse";
                            break;
                        case "poly":
                            $areatype = "Bounded";
                            break;
                    }
                    $attrs = [
                        "respident" => "IM",
                        "areatype" => $areatype
                    ];
                    $a_xml_writer->xmlElement("varequal", $attrs, $index);
                    if ($answer->getPoints() >= $answer->getPointsUnchecked()) {
                        $a_xml_writer->xmlEndTag("not");
                    }
                    if ($index > 0) {
                        $a_xml_writer->xmlEndTag("or");
                    }
                }
            }
            $a_xml_writer->xmlEndTag("conditionvar");
            // qti displayfeedback
            $attrs = [
                "feedbacktype" => "Response",
                "linkrefid" => "response_onenotcorrect"
            ];
            $a_xml_writer->xmlElement("displayfeedback", $attrs);
            $a_xml_writer->xmlEndTag("respcondition");
        }

        $a_xml_writer->xmlEndTag("resprocessing");

        // PART III: qti itemfeedback
        foreach ($this->object->getAnswers() as $index => $answer) {
            $linkrefid = "response_$index";
            $attrs = [
                "ident" => $linkrefid,
                "view" => "All"
            ];
            $a_xml_writer->xmlStartTag("itemfeedback", $attrs);
            // qti flow_mat
            $a_xml_writer->xmlStartTag("flow_mat");
            $fb = $this->object->feedbackOBJ->getSpecificAnswerFeedbackExportPresentation(
                $this->object->getId(),
                0,
                $index
            );
            $this->addQTIMaterial($a_xml_writer, $fb);
            $a_xml_writer->xmlEndTag("flow_mat");
            $a_xml_writer->xmlEndTag("itemfeedback");
        }
        if (strlen($feedback_allcorrect)) {
            $attrs = [
                "ident" => "response_allcorrect",
                "view" => "All"
            ];
            $a_xml_writer->xmlStartTag("itemfeedback", $attrs);
            // qti flow_mat
            $a_xml_writer->xmlStartTag("flow_mat");
            $this->addQTIMaterial($a_xml_writer, $feedback_allcorrect);
            $a_xml_writer->xmlEndTag("flow_mat");
            $a_xml_writer->xmlEndTag("itemfeedback");
        }
        if (strlen($feedback_onenotcorrect)) {
            $attrs = [
                "ident" => "response_onenotcorrect",
                "view" => "All"
            ];
            $a_xml_writer->xmlStartTag("itemfeedback", $attrs);
            // qti flow_mat
            $a_xml_writer->xmlStartTag("flow_mat");
            $this->addQTIMaterial($a_xml_writer, $feedback_onenotcorrect);
            $a_xml_writer->xmlEndTag("flow_mat");
            $a_xml_writer->xmlEndTag("itemfeedback");
        }

        $a_xml_writer = $this->addSolutionHints($a_xml_writer);

        $a_xml_writer->xmlEndTag("item");
        $a_xml_writer->xmlEndTag("questestinterop");

        $xml = $a_xml_writer->xmlDumpMem(false);
        if (!$a_include_header) {
            $pos = strpos($xml, "?>");
            $xml = substr($xml, $pos + 2);
        }
        return $xml;
    }
}
