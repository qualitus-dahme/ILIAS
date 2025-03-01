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
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package     Modules/Test
 */
class assKprimChoiceImport extends assQuestionImport
{
    /**
     * @var assKprimChoice
     */
    public $object;

    public function fromXML(&$item, $questionpool_id, &$tst_id, &$tst_object, &$question_counter, $import_mapping): array
    {
        global $DIC;
        $ilUser = $DIC['ilUser'];

        ilSession::clear('import_mob_xhtml');

        $shuffle = 0;
        $answers = array();

        $presentation = $item->getPresentation();
        foreach ($presentation->order as $entry) {
            switch ($entry["type"]) {
                case "response":
                    $response = $presentation->response[$entry["index"]];
                    $rendertype = $response->getRenderType();
                    switch (strtolower(get_class($response->getRenderType()))) {
                        case "ilqtirenderchoice":
                            $shuffle = $rendertype->getShuffle();
                            $answerorder = 0;
                            $foundimage = false;
                            foreach ($rendertype->response_labels as $response_label) {
                                $ident = $response_label->getIdent();
                                $answertext = "";
                                $answerimage = array();
                                foreach ($response_label->material as $mat) {
                                    $embedded = false;
                                    for ($m = 0; $m < $mat->getMaterialCount(); $m++) {
                                        $foundmat = $mat->getMaterial($m);
                                        if (strcmp($foundmat["type"], "mattext") == 0) {
                                        }
                                        if (strcmp($foundmat["type"], "matimage") == 0) {
                                            if (strlen($foundmat["material"]->getEmbedded())) {
                                                $embedded = true;
                                            }
                                        }
                                    }
                                    if ($embedded) {
                                        for ($m = 0; $m < $mat->getMaterialCount(); $m++) {
                                            $foundmat = $mat->getMaterial($m);
                                            if (strcmp($foundmat["type"], "mattext") == 0) {
                                                $answertext .= $foundmat["material"]->getContent();
                                            }
                                            if (strcmp($foundmat["type"], "matimage") == 0) {
                                                $foundimage = true;
                                                $answerimage = array(
                                                    "imagetype" => $foundmat["material"]->getImageType(),
                                                    "label" => $foundmat["material"]->getLabel(),
                                                    "content" => $foundmat["material"]->getContent()
                                                );
                                            }
                                        }
                                    } else {
                                        $answertext = $this->QTIMaterialToString($mat);
                                    }
                                }

                                $answers[$ident] = array(
                                    "answertext" => $answertext,
                                    "imagefile" => $answerimage,
                                    "answerorder" => $ident
                                );
                            }
                            break;
                    }
                    break;
            }
        }

        $feedbacks = array();
        $feedbacksgeneric = array();

        foreach ($item->resprocessing as $resprocessing) {
            foreach ($resprocessing->outcomes->decvar as $decvar) {
                if ($decvar->getVarname() == 'SCORE') {
                    $this->object->setPoints($decvar->getMaxvalue());

                    if ($decvar->getMinvalue() > 0) {
                        $this->object->setScorePartialSolutionEnabled(true);
                    } else {
                        $this->object->setScorePartialSolutionEnabled(false);
                    }
                }
            }

            foreach ($resprocessing->respcondition as $respcondition) {
                if (!count($respcondition->setvar)) {
                    foreach ($respcondition->getConditionvar()->varequal as $varequal) {
                        $ident = $varequal->respident;
                        $answers[$ident]['correctness'] = (bool) $varequal->getContent();

                        break;
                    }

                    foreach ($respcondition->displayfeedback as $feedbackpointer) {
                        if (strlen($feedbackpointer->getLinkrefid())) {
                            foreach ($item->itemfeedback as $ifb) {
                                if (strcmp($ifb->getIdent(), $feedbackpointer->getLinkrefid()) == 0) {
                                    // found a feedback for the identifier
                                    if (count($ifb->material)) {
                                        foreach ($ifb->material as $material) {
                                            $feedbacks[$ident] = $material;
                                        }
                                    }
                                    if ((count($ifb->flow_mat) > 0)) {
                                        foreach ($ifb->flow_mat as $fmat) {
                                            if (count($fmat->material)) {
                                                foreach ($fmat->material as $material) {
                                                    $feedbacks[$ident] = $material;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    foreach ($respcondition->displayfeedback as $feedbackpointer) {
                        if (strlen($feedbackpointer->getLinkrefid())) {
                            foreach ($item->itemfeedback as $ifb) {
                                if ($ifb->getIdent() == "response_allcorrect") {
                                    // found a feedback for the identifier
                                    if (count($ifb->material)) {
                                        foreach ($ifb->material as $material) {
                                            $feedbacksgeneric[1] = $material;
                                        }
                                    }
                                    if ((count($ifb->flow_mat) > 0)) {
                                        foreach ($ifb->flow_mat as $fmat) {
                                            if (count($fmat->material)) {
                                                foreach ($fmat->material as $material) {
                                                    $feedbacksgeneric[1] = $material;
                                                }
                                            }
                                        }
                                    }
                                } elseif ($ifb->getIdent() == "response_onenotcorrect") {
                                    // found a feedback for the identifier
                                    if (count($ifb->material)) {
                                        foreach ($ifb->material as $material) {
                                            $feedbacksgeneric[0] = $material;
                                        }
                                    }
                                    if ((count($ifb->flow_mat) > 0)) {
                                        foreach ($ifb->flow_mat as $fmat) {
                                            if (count($fmat->material)) {
                                                foreach ($fmat->material as $material) {
                                                    $feedbacksgeneric[0] = $material;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->addGeneralMetadata($item);
        $this->object->setTitle($item->getTitle());
        $this->object->setNrOfTries((int) $item->getMaxattempts());
        $this->object->setComment($item->getComment());
        $this->object->setAuthor($item->getAuthor());
        $this->object->setOwner($ilUser->getId());
        $this->object->setQuestion($this->QTIMaterialToString($item->getQuestiontext()));
        $this->object->setObjId($questionpool_id);
        $this->object->setShuffleAnswersEnabled($shuffle);
        $this->object->setAnswerType($item->getMetadataEntry('answer_type'));
        $this->object->setOptionLabel($item->getMetadataEntry('option_label_setting'));
        $this->object->setCustomTrueOptionLabel($item->getMetadataEntry('custom_true_option_label'));
        $this->object->setCustomFalseOptionLabel($item->getMetadataEntry('custom_false_option_label'));
        $this->object->setThumbSize(
            $this->deduceThumbSizeFromImportValue((int) $item->getMetadataEntry('thumb_size'))
        );

        $this->object->saveToDb();

        foreach ($answers as $answerData) {
            $answer = new ilAssKprimChoiceAnswer();
            $answer->setImageFsDir($this->object->getImagePath());
            $answer->setImageWebDir($this->object->getImagePathWeb());

            $answer->setPosition($answerData['answerorder']);
            $answer->setAnswertext($answerData['answertext']);
            $answer->setCorrectness($answerData['correctness']);

            if (isset($answerData['imagefile']['label'])) {
                $answer->setImageFile($answerData['imagefile']['label']);
            }

            $this->object->addAnswer($answer);
        }
        // additional content editing mode information
        $this->object->setAdditionalContentEditingMode(
            $this->fetchAdditionalContentEditingModeInformation($item)
        );

        $this->object->saveToDb();

        foreach ($answers as $answer) {
            if (is_array($answer["imagefile"]) && (count($answer["imagefile"]) > 0)) {
                $image = base64_decode($answer["imagefile"]["content"]);
                $imagepath = $this->object->getImagePath();
                if (!file_exists($imagepath)) {
                    ilFileUtils::makeDirParents($imagepath);
                }
                $imagepath .= $answer["imagefile"]["label"];
                if ($fh = fopen($imagepath, "wb")) {
                    $imagefile = fwrite($fh, $image);
                    fclose($fh);
                    $this->object->generateThumbForFile(
                        $answer["imagefile"]["label"],
                        $this->object->getImagePath(),
                        $this->object->getThumbSize()
                    );
                }
            }
        }

        $feedbackSetting = $item->getMetadataEntry('feedback_setting');
        if (!is_null($feedbackSetting)) {
            $this->object->feedbackOBJ->saveSpecificFeedbackSetting($this->object->getId(), $feedbackSetting);
            $this->object->setSpecificFeedbackSetting($feedbackSetting);
        }

        // handle the import of media objects in XHTML code
        foreach ($feedbacks as $ident => $material) {
            $m = $this->QTIMaterialToString($material);
            $feedbacks[$ident] = $m;
        }
        foreach ($feedbacksgeneric as $correctness => $material) {
            $m = $this->QTIMaterialToString($material);
            $feedbacksgeneric[$correctness] = $m;
        }
        $questiontext = $this->object->getQuestion();
        $answers = $this->object->getAnswers();
        if (is_array(ilSession::get("import_mob_xhtml"))) {
            foreach (ilSession::get("import_mob_xhtml") as $mob) {
                if ($tst_id > 0) {
                    $importfile = $this->getTstImportArchivDirectory() . '/' . $mob["uri"];
                } else {
                    $importfile = $this->getQplImportArchivDirectory() . '/' . $mob["uri"];
                }

                global $DIC; /* @var ILIAS\DI\Container $DIC */
                $DIC['ilLog']->write(__METHOD__ . ': import mob from dir: ' . $importfile);

                $media_object = ilObjMediaObject::_saveTempFileAsMediaObject(basename($importfile), $importfile, false);
                ilObjMediaObject::_saveUsage($media_object->getId(), "qpl:html", $this->object->getId());
                $questiontext = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $questiontext);
                foreach ($answers as $key => $value) {
                    $answer_obj = &$answers[$key];
                    $answer_obj->setAnswertext(str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $answer_obj->getAnswertext()));
                }
                foreach ($feedbacks as $ident => $material) {
                    $feedbacks[$ident] = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $material);
                }
                foreach ($feedbacksgeneric as $correctness => $material) {
                    $feedbacksgeneric[$correctness] = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $material);
                }
            }
        }
        $this->object->setQuestion(ilRTE::_replaceMediaObjectImageSrc($questiontext, 1));
        foreach ($answers as $key => $value) {
            $answer_obj = &$answers[$key];
            $answer_obj->setAnswertext(ilRTE::_replaceMediaObjectImageSrc($answer_obj->getAnswertext(), 1));
        }
        foreach ($feedbacks as $ident => $material) {
            $this->object->feedbackOBJ->importSpecificAnswerFeedback(
                $this->object->getId(),
                0,
                $ident,
                ilRTE::_replaceMediaObjectImageSrc($material, 1)
            );
        }
        foreach ($feedbacksgeneric as $correctness => $material) {
            $this->object->feedbackOBJ->importGenericFeedback(
                $this->object->getId(),
                $correctness,
                ilRTE::_replaceMediaObjectImageSrc($material, 1)
            );
        }
        $this->object->saveToDb();
        $this->importSuggestedSolutions($this->object->getId(), $item->suggested_solutions);
        if ($tst_id > 0) {
            $q_1_id = $this->object->getId();
            $question_id = $this->object->duplicate(true, "", "", -1, $tst_id);
            $tst_object->questions[$question_counter++] = $question_id;
            $import_mapping[$item->getIdent()] = array("pool" => $q_1_id, "test" => $question_id);
        } else {
            $import_mapping[$item->getIdent()] = array("pool" => $this->object->getId(), "test" => 0);
        }
        return $import_mapping;
    }
}
