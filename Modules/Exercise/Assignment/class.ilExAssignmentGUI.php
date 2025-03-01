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

use ILIAS\DI\UIServices;
use ILIAS\Exercise\InternalService;
use ILIAS\Exercise\Assignment\Mandatory\MandatoryAssignmentsManager;

/**
 * GUI class for exercise assignments
 *
 * @author Alexander Killing <killing@leifos.de>
 */
class ilExAssignmentGUI
{
    protected \ILIAS\Exercise\InternalGUIService $gui;
    protected \ILIAS\MediaObjects\MediaType\MediaTypeManager $media_type;
    protected ilLanguage $lng;
    protected ilObjUser $user;
    protected ilCtrl $ctrl;
    protected ilObjExercise $exc;
    protected int $current_ass_id;
    protected InternalService $service;
    protected MandatoryAssignmentsManager $mandatory_manager;
    protected UIServices $ui;
    protected int $requested_ass_id;


    /**
     * @throws ilExcUnknownAssignmentTypeException
     */
    public function __construct(
        ilObjExercise $a_exc,
        InternalService $service
    ) {
        /** @var \ILIAS\DI\Container $DIC */
        global $DIC;

        $request = $DIC->exercise()->internal()->gui()->request();
        $this->requested_ass_id = $request->getAssId();

        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->ctrl = $DIC->ctrl();
        $this->ui = $DIC->ui();

        $this->exc = $a_exc;
        $this->service = $service;
        $this->mandatory_manager = $service->domain()->assignment()->mandatoryAssignments($this->exc);
        $this->media_type = $DIC->mediaObjects()->internal()->domain()->mediaType();
        $this->gui = $DIC->exercise()
            ->internal()
            ->gui();
    }

    /**
     * Get assignment header for overview
     * @throws ilDateTimeException
     */
    public function getOverviewHeader(ilExAssignment $a_ass): string
    {
        $lng = $this->lng;
        $ilUser = $this->user;

        $lng->loadLanguageModule("exc");

        $state = ilExcAssMemberState::getInstanceByIds($a_ass->getId(), $ilUser->getId());

        $tpl = new ilTemplate("tpl.assignment_head.html", true, true, "Modules/Exercise");

        // we are completely ignoring the extended deadline here

        // :TODO: meaning of "ended on"
        if ($state->exceededOfficialDeadline()) {
            $tpl->setCurrentBlock("prop");
            $tpl->setVariable("PROP", $lng->txt("exc_ended_on"));
            $tpl->setVariable("PROP_VAL", $state->getCommonDeadlinePresentation());
            $tpl->parseCurrentBlock();

            // #14077						// this currently shows the feedback deadline during grace period
            if ($state->getPeerReviewDeadline()) {
                $tpl->setCurrentBlock("prop");
                $tpl->setVariable("PROP", $lng->txt("exc_peer_review_deadline"));
                $tpl->setVariable("PROP_VAL", $state->getPeerReviewDeadlinePresentation());
                $tpl->parseCurrentBlock();
            }
        } elseif (!$state->hasGenerallyStarted()) {
            $tpl->setCurrentBlock("prop");
            if ($state->getRelativeDeadline()) {
                $tpl->setVariable("PROP", $lng->txt("exc_earliest_start_time"));
            } else {
                $tpl->setVariable("PROP", $lng->txt("exc_starting_on"));
            }
            $tpl->setVariable("PROP_VAL", $state->getGeneralStartPresentation());
            $tpl->parseCurrentBlock();
        } else {
            if ($state->getCommonDeadline() > 0) {
                $tpl->setCurrentBlock("prop");
                $tpl->setVariable("PROP", $lng->txt("exc_time_to_send"));
                $tpl->setVariable("PROP_VAL", $state->getRemainingTimePresentation());
                $tpl->parseCurrentBlock();

                $tpl->setCurrentBlock("prop");
                $tpl->setVariable("PROP", $lng->txt("exc_edit_until"));
                $tpl->setVariable("PROP_VAL", $state->getCommonDeadlinePresentation());
                $tpl->parseCurrentBlock();
            } elseif ($state->getRelativeDeadline()) {		// if we only have a relative deadline (not started yet)
                $tpl->setCurrentBlock("prop");
                $tpl->setVariable("PROP", $lng->txt("exc_rem_time_after_start"));
                $tpl->setVariable("PROP_VAL", $state->getRelativeDeadlinePresentation());
                $tpl->parseCurrentBlock();

                if ($state->getLastSubmissionOfRelativeDeadline()) {		// if we only have a relative deadline (not started yet)
                    $tpl->setCurrentBlock("prop");
                    $tpl->setVariable("PROP", $lng->txt("exc_rel_last_submission"));
                    $tpl->setVariable("PROP_VAL", $state->getLastSubmissionOfRelativeDeadlinePresentation());
                    $tpl->parseCurrentBlock();
                }
            }


            if ($state->getIndividualDeadline() > 0) {
                $tpl->setCurrentBlock("prop");
                $tpl->setVariable("PROP", $lng->txt("exc_individual_deadline"));
                $tpl->setVariable("PROP_VAL", $state->getIndividualDeadlinePresentation());
                $tpl->parseCurrentBlock();
            }
        }

        $mand = "";
        if ($this->mandatory_manager->isMandatoryForUser($a_ass->getId(), $this->user->getId())) {
            $mand = " (" . $lng->txt("exc_mandatory") . ")";
        }
        $tpl->setVariable("TITLE", $a_ass->getTitle() . $mand);

        // status icon
        $tpl->setVariable(
            "ICON_STATUS",
            $this->getIconForStatus(
                $a_ass->getMemberStatus()->getStatus(),
                ilLPStatusIcons::ICON_VARIANT_SHORT
            )
        );

        return $tpl->get();
    }

    /**
     * Get assignment body for overview
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilDateTimeException
     */
    public function getOverviewBody(ilExAssignment $a_ass): string
    {
        global $DIC;

        $ilUser = $DIC->user();

        $this->current_ass_id = $a_ass->getId();

        $tpl = new ilTemplate("tpl.assignment_body.html", true, true, "Modules/Exercise");

        $state = ilExcAssMemberState::getInstanceByIds($a_ass->getId(), $ilUser->getId());

        $info = new ilInfoScreenGUI(null);
        $info->setTableClass("");
        if ($state->areInstructionsVisible()) {
            $this->addInstructions($info, $a_ass);
            $this->addFiles($info, $a_ass);
        }

        $this->addSchedule($info, $a_ass);

        if ($state->hasSubmissionStarted()) {
            $this->addSubmission($info, $a_ass);
        }

        $tpl->setVariable("CONTENT", $info->getHTML());

        return $tpl->get();
    }


    protected function addInstructions(
        ilInfoScreenGUI $a_info,
        ilExAssignment $a_ass
    ): void {
        $ilUser = $this->user;
        $info = new ilExAssignmentInfo($a_ass->getId(), $ilUser->getId());
        $inst = $info->getInstructionInfo();
        if (count($inst) > 0) {
            $a_info->addSection($inst["instruction"]["txt"]);
            $a_info->addProperty("", $inst["instruction"]["value"]);
        }
    }

    /**
     * @throws ilDateTimeException|ilExcUnknownAssignmentTypeException
     */
    protected function addSchedule(
        ilInfoScreenGUI $a_info,
        ilExAssignment $a_ass
    ): void {
        $lng = $this->lng;
        $ilUser = $this->user;
        $ilCtrl = $this->ctrl;

        $info = new ilExAssignmentInfo($a_ass->getId(), $ilUser->getId());
        $schedule = $info->getScheduleInfo();

        $state = ilExcAssMemberState::getInstanceByIds($a_ass->getId(), $ilUser->getId());

        $a_info->addSection($lng->txt("exc_schedule"));
        if ($state->getGeneralStart() > 0) {
            $a_info->addProperty($schedule["start_time"]["txt"], $schedule["start_time"]["value"]);
        }


        if ($state->getCommonDeadline()) {		// if we have a common deadline (target timestamp)
            $a_info->addProperty($schedule["until"]["txt"], $schedule["until"]["value"]);
        } elseif ($state->getRelativeDeadline()) {		// if we only have a relative deadline (not started yet)
            $but = "";
            if ($state->hasGenerallyStarted()) {
                $ilCtrl->setParameterByClass("ilobjexercisegui", "ass_id", $a_ass->getId());
                $but = $this->ui->factory()->button()->primary($lng->txt("exc_start_assignment"), $ilCtrl->getLinkTargetByClass("ilobjexercisegui", "startAssignment"));
                $ilCtrl->setParameterByClass("ilobjexercisegui", "ass_id", $this->requested_ass_id);
                $but = $this->ui->renderer()->render($but);
            }

            $a_info->addProperty($schedule["time_after_start"]["txt"], $schedule["time_after_start"]["value"] . " " . $but);
            if ($state->getLastSubmissionOfRelativeDeadline()) {		// if we only have a relative deadline (not started yet)
                $a_info->addProperty(
                    $lng->txt("exc_rel_last_submission"),
                    $state->getLastSubmissionOfRelativeDeadlinePresentation()
                );
            }
        }

        if ($state->getOfficialDeadline() > $state->getCommonDeadline()) {
            $a_info->addProperty($schedule["individual_deadline"]["txt"], $schedule["individual_deadline"]["value"]);
        }

        if ($state->hasSubmissionStarted()) {
            $a_info->addProperty($schedule["time_to_send"]["txt"], $schedule["time_to_send"]["value"]);
        }
    }

    protected function addPublicSubmissions(
        ilInfoScreenGUI $a_info,
        ilExAssignment $a_ass
    ): void {
        $lng = $this->lng;
        $ilUser = $this->user;

        $state = ilExcAssMemberState::getInstanceByIds($a_ass->getId(), $ilUser->getId());

        // submissions are visible, even if other users may still have a larger individual deadline
        if ($state->hasSubmissionEnded()) {
            $b = $this->gui->link(
                $lng->txt("exc_list_submission"),
                $this->getSubmissionLink("listPublicSubmissions")
            )->emphasised()
                ->render();
            $a_info->addProperty($lng->txt("exc_public_submission"), $b);
        } else {
            $a_info->addProperty(
                $lng->txt("exc_public_submission"),
                $lng->txt("exc_msg_public_submission")
            );
        }
    }

    protected function addFiles(
        ilInfoScreenGUI $a_info,
        ilExAssignment $a_ass
    ): void {
        $lng = $this->lng;
        $lng->loadLanguageModule("exc");
        $files = $a_ass->getFiles();
        if (count($files) > 0) {
            $a_info->addSection($lng->txt("exc_files"));

            global $DIC;

            //file has -> name,fullpath,size,ctime
            $cnt = 0;
            foreach ($files as $file) {
                $cnt++;
                // get mime type
                $mime = $file['mime'] ?? ilObjMediaObject::getMimeType($file['fullpath']);

                $ui_factory = $DIC->ui()->factory();
                $ui_renderer = $DIC->ui()->renderer();

                $output_filename = htmlspecialchars($file['name']);

                if ($this->media_type->isImage($mime)) {
                    $item_id = "il-ex-modal-img-" . $a_ass->getId() . "-" . $cnt;


                    $image = $ui_renderer->render($ui_factory->image()->responsive($file['fullpath'], $output_filename));
                    $image_lens = ilUtil::getImagePath("media/enlarge.svg");

                    $modal = ilModalGUI::getInstance();
                    $modal->setId($item_id);
                    $modal->setType(ilModalGUI::TYPE_LARGE);
                    $modal->setBody($image);
                    $modal->setHeading($output_filename);
                    $modal = $modal->getHTML();

                    $img_tpl = new ilTemplate("tpl.image_file.html", true, true, "Modules/Exercise");
                    $img_tpl->setCurrentBlock("image_content");
                    $img_tpl->setVariable("MODAL", $modal);
                    $img_tpl->setVariable("ITEM_ID", $item_id);
                    $img_tpl->setVariable("IMAGE", $image);
                    $img_tpl->setvariable("IMAGE_LENS", $image_lens);
                    $img_tpl->setvariable("ALT_LENS", $lng->txt("exc_fullscreen"));
                    $img_tpl->parseCurrentBlock();

                    $a_info->addProperty($output_filename, $img_tpl->get());
                } elseif ($this->media_type->isAudio($mime) || $this->media_type->isVideo($mime)) {
                    $media_tpl = new ilTemplate("tpl.media_file.html", true, true, "Modules/Exercise");

                    if ($this->media_type->isAudio($mime)) {
                        $p = $ui_factory->player()->audio($file['fullpath']);
                    } else {
                        $p = $ui_factory->player()->video($file['fullpath']);
                    }
                    $media_tpl->setVariable("MEDIA", $ui_renderer->render($p));

                    $but = $ui_factory->button()->shy(
                        $lng->txt("download"),
                        $this->getSubmissionLink("downloadFile", array("file" => urlencode($file["name"])))
                    );
                    $media_tpl->setVariable("DOWNLOAD_BUTTON", $ui_renderer->render($but));
                    $a_info->addProperty($output_filename, $media_tpl->get());
                } else {
                    $a_info->addProperty($output_filename, $lng->txt("download"), $this->getSubmissionLink("downloadFile", array("file" => urlencode($file["name"]))));
                }
            }
        }
    }

    /**
     * @throws ilCtrlException
     * @throws ilDateTimeException
     */
    protected function addSubmission(
        ilInfoScreenGUI $a_info,
        ilExAssignment $a_ass
    ): void {
        $lng = $this->lng;
        $ilUser = $this->user;

        $state = ilExcAssMemberState::getInstanceByIds($a_ass->getId(), $ilUser->getId());

        $a_info->addSection($lng->txt("exc_your_submission"));

        $submission = new ilExSubmission($a_ass, $ilUser->getId());

        ilExSubmissionGUI::getOverviewContent($a_info, $submission, $this->exc);

        $last_sub = null;
        if ($submission->hasSubmitted()) {
            $last_sub = $submission->getLastSubmission();
            if ($last_sub) {
                $last_sub = ilDatePresentation::formatDate(new ilDateTime($last_sub, IL_CAL_DATETIME));
                $a_info->addProperty($lng->txt("exc_last_submission"), $last_sub);
            }
        }

        if ($this->exc->getShowSubmissions()) {
            $this->addPublicSubmissions($a_info, $a_ass);
        }

        ilExPeerReviewGUI::getOverviewContent($a_info, $submission);

        // global feedback / sample solution
        if ($a_ass->getFeedbackDate() == ilExAssignment::FEEDBACK_DATE_DEADLINE) {
            $show_global_feedback = ($state->hasSubmissionEndedForAllUsers() && $a_ass->getFeedbackFile());
        }
        //If it is not well configured...(e.g. show solution before deadline)
        //the user can get the solution before he summit it.
        //we can check in the elseif $submission->hasSubmitted()
        elseif ($a_ass->getFeedbackDate() == ilExAssignment::FEEDBACK_DATE_CUSTOM) {
            $show_global_feedback = ($a_ass->afterCustomDate() && $a_ass->getFeedbackFile());
        } else {
            $show_global_feedback = ($last_sub && $a_ass->getFeedbackFile());
        }
        $this->addSubmissionFeedback($a_info, $a_ass, $submission->getFeedbackId(), $show_global_feedback);
    }

    protected function addSubmissionFeedback(
        ilInfoScreenGUI $a_info,
        ilExAssignment $a_ass,
        string $a_feedback_id,
        bool $a_show_global_feedback
    ): void {
        $lng = $this->lng;

        $feedback_file_manager = $this->service->domain()->assignment()->tutorFeedbackFile($a_ass->getId());
        $cnt_files = $feedback_file_manager->count($this->user->getId());
        $lpcomment = $a_ass->getMemberStatus()->getComment();
        $mark = $a_ass->getMemberStatus()->getMark();
        $status = $a_ass->getMemberStatus()->getStatus();

        if ($lpcomment != "" ||
            $mark != "" ||
            $status != "notgraded" ||
            $cnt_files > 0 ||
            $a_show_global_feedback) {
            $a_info->addSection($lng->txt("exc_feedback_from_tutor"));
            if ($lpcomment != "") {
                $a_info->addProperty(
                    $lng->txt("exc_comment"),
                    nl2br($lpcomment)
                );
            }
            if ($mark != "") {
                $a_info->addProperty(
                    $lng->txt("exc_mark"),
                    $mark
                );
            }

            if ($status != "" && $status != "notgraded") {
                $img = $this->getIconForStatus($status);
                $a_info->addProperty(
                    $lng->txt("status"),
                    $img . " " . $lng->txt("exc_" . $status)
                );
            }

            if ($cnt_files > 0) {
                $a_info->addSection($lng->txt("exc_fb_files") .
                    '<a id="fb' . $a_ass->getId() . '"></a>');

                if ($cnt_files > 0) {
                    $files = $feedback_file_manager->getFiles($this->user->getId());
                    foreach ($files as $file) {
                        $a_info->addProperty(
                            $file,
                            $lng->txt("download"),
                            $this->getSubmissionLink("downloadFeedbackFile", array("file" => urlencode($file)))
                        );
                    }
                }
            }

            // #15002 - global feedback
            if ($a_show_global_feedback) {
                $a_info->addSection($lng->txt("exc_global_feedback_file"));

                $a_info->addProperty(
                    $a_ass->getFeedbackFile(),
                    $lng->txt("download"),
                    $this->getSubmissionLink("downloadGlobalFeedbackFile")
                );
            }
        }
    }

    /**
     * Get time string for deadline
     * @throws ilDateTimeException
     */
    public function getTimeString(int $a_deadline): string
    {
        $lng = $this->lng;

        if ($a_deadline == 0) {
            return $lng->txt("exc_submit_convenience_no_deadline");
        }

        if ($a_deadline - time() <= 0) {
            $time_str = $lng->txt("exc_time_over_short");
        } else {
            $time_str = ilLegacyFormElementsUtil::period2String(new ilDateTime($a_deadline, IL_CAL_UNIX));
        }

        return $time_str;
    }

    protected function getSubmissionLink(
        string $a_cmd,
        array $a_params = null
    ): string {
        $ilCtrl = $this->ctrl;

        if (is_array($a_params)) {
            foreach ($a_params as $name => $value) {
                $ilCtrl->setParameterByClass("ilexsubmissiongui", $name, $value);
            }
        }

        $ilCtrl->setParameterByClass("ilexsubmissiongui", "ass_id", $this->current_ass_id);
        $url = $ilCtrl->getLinkTargetByClass([ilAssignmentPresentationGUI::class, "ilexsubmissiongui"], $a_cmd);
        $ilCtrl->setParameterByClass("ilexsubmissiongui", "ass_id", "");

        if (is_array($a_params)) {
            foreach ($a_params as $name => $value) {
                $ilCtrl->setParameterByClass("ilexsubmissiongui", $name, "");
            }
        }

        return $url;
    }

    /**
     * Get the rendered icon for a status (failed, passed or not graded).
     */
    protected function getIconForStatus(string $status, int $variant = ilLPStatusIcons::ICON_VARIANT_LONG): string
    {
        $icons = ilLPStatusIcons::getInstance($variant);
        $lng = $this->lng;

        switch ($status) {
            case "passed":
                return $icons->renderIcon(
                    $icons->getImagePathCompleted(),
                    $lng->txt("exc_" . $status)
                );

            case "failed":
                return $icons->renderIcon(
                    $icons->getImagePathFailed(),
                    $lng->txt("exc_" . $status)
                );

            default:
                return $icons->renderIcon(
                    $icons->getImagePathNotAttempted(),
                    $lng->txt("exc_" . $status)
                );
        }
    }

    ////
    //// Listing panels
    ////
}
