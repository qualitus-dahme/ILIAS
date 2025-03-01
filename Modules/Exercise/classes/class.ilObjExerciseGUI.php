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

use ILIAS\Exercise\InternalService;
use ILIAS\Exercise;

/**
 * @author       Stefan Meyer <smeyer@databay.de>
 * @author       Alexander Killing <killing@leifos.de>
 * @author       Michael Jansen <mjansen@databay.de>
 * @ilCtrl_Calls ilObjExerciseGUI: ilPermissionGUI, ilLearningProgressGUI, ilInfoScreenGUI
 * @ilCtrl_Calls ilObjExerciseGUI: ilObjectCopyGUI, ilExportGUI
 * @ilCtrl_Calls ilObjExerciseGUI: ilCommonActionDispatcherGUI, ilCertificateGUI
 * @ilCtrl_Calls ilObjExerciseGUI: ilExAssignmentEditorGUI, ilAssignmentPresentationGUI
 * @ilCtrl_Calls ilObjExerciseGUI: ilExerciseManagementGUI, ilExcCriteriaCatalogueGUI, ilObjectMetaDataGUI, ilPortfolioExerciseGUI, ilExcRandomAssignmentGUI
 */
class ilObjExerciseGUI extends ilObjectGUI
{
    protected ilCertificateDownloadValidator $certificateDownloadValidator;
    protected \ILIAS\DI\UIServices $ui;
    protected Exercise\Assignment\AssignmentManager $ass_manager;
    private Exercise\Assignment\ItemBuilderUI $item_builder;
    protected Exercise\Notification\NotificationManager $notification;
    protected Exercise\InternalGUIService $gui;
    protected ilTabsGUI $tabs;
    protected ilHelpGUI $help;
    protected ?ilExAssignment $ass = null;
    protected InternalService $service;
    protected Exercise\GUIRequest $exercise_request;
    protected Exercise\InternalGUIService $exercise_ui;
    protected ?int $requested_ass_id;
    protected int $lp_user_id;
    protected string $requested_sort_order;
    protected string $requested_sort_by;
    protected int $requested_offset;
    protected int $requested_ref_id;
    protected int $requested_ass_id_goto;

    /**
     * @throws ilExerciseException
     */
    public function __construct($a_data, int $a_id, bool $a_call_by_reference)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->help = $DIC["ilHelp"];
        $this->locator = $DIC["ilLocator"];
        $this->tpl = $DIC["tpl"];
        $this->toolbar = $DIC->toolbar();
        $lng = $DIC->language();

        $this->lng->loadLanguageModule('cert');

        $this->type = "exc";
        parent::__construct($a_data, $a_id, $a_call_by_reference, false);

        $lng->loadLanguageModule("exercise");
        $lng->loadLanguageModule("exc");
        $this->ctrl->saveParameter($this, ["ass_id", "mode", "from_overview"]);

        $this->service = $DIC->exercise()->internal();
        $this->gui = $this->service->gui();
        $this->exercise_request = $DIC->exercise()->internal()->gui()->request();
        $this->exercise_ui = $DIC->exercise()->internal()->gui();
        $this->requested_ass_id = $this->exercise_request->getAssId();

        if ($this->requested_ass_id > 0 && is_object($this->object) && ilExAssignment::lookupExerciseId(
            $this->requested_ass_id
        ) === $this->object->getId()) {
            $this->ass = $this->exercise_request->getAssignment();
        } elseif ($this->requested_ass_id > 0) {
            throw new ilExerciseException("Assignment ID does not match Exercise.");
        }
        $this->lp_user_id = ($this->exercise_request->getUserId() > 0 && $this->access->checkAccess("read_learning_progress", "", $this->exercise_request->getRefId()))
            ? $this->exercise_request->getUserId()
            : $this->user->getId();
        $this->requested_sort_order = $this->exercise_request->getSortOrder();
        $this->requested_sort_by = $this->exercise_request->getSortBy();
        $this->requested_offset = $this->exercise_request->getOffset();
        $this->requested_ref_id = $this->exercise_request->getRefId();
        $this->requested_ass_id_goto = $this->exercise_request->getAssIdGoto();
        $this->ui = $this->service->gui()->ui();
        $this->certificateDownloadValidator = new ilCertificateDownloadValidator();
        $this->notification = $this->service->domain()->notification($this->requested_ref_id);

        if ($this->object) {
            $this->ass_manager = $this->service->domain()->assignment()->assignments(
                $this->object->getId(),
                $this->user->getId()
            );
            $this->item_builder = $this->service->gui()->assignment()->itemBuilder(
                $this->object,
                $this->service->domain()->assignment()->mandatoryAssignments($this->object)
            );
        }
    }

    /**
     * @throws ilException
     * @throws ilObjectException
     */
    public function executeCommand(): void
    {
        $ilCtrl = $this->ctrl;
        $ilTabs = $this->tabs;

        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd();
        $this->prepareOutput();

        /** @var ilObjExercise $exc */
        $exc = $this->object;

        if (!$this->getCreationMode() && isset($this->object)) {
            $this->gui->permanentLink()->setPermanentLink();
        }

        //echo "-".$next_class."-".$cmd."-"; exit;
        switch ($next_class) {
            case "ilinfoscreengui":
                $ilTabs->activateTab("info");
                $this->infoScreen();    // forwards command
                break;

            case 'ilpermissiongui':
                $ilTabs->activateTab("permissions");
                $perm_gui = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($perm_gui);
                break;

            case "illearningprogressgui":
                $ilTabs->activateTab("learning_progress");
                $new_gui = new ilLearningProgressGUI(
                    ilLearningProgressBaseGUI::LP_CONTEXT_REPOSITORY,
                    $this->object->getRefId(),
                    $this->lp_user_id
                );
                $this->ctrl->forwardCommand($new_gui);
                $this->tabs_gui->setTabActive('learning_progress');
                break;

            case 'ilobjectcopygui':
                $ilCtrl->saveParameter($this, 'new_type');
                $ilCtrl->setReturnByClass(get_class($this), 'create');

                $cp = new ilObjectCopyGUI($this);
                $cp->setType('exc');
                $this->ctrl->forwardCommand($cp);
                break;

            case "ilexportgui":
                $ilTabs->activateTab("export");
                $exp_gui = new ilExportGUI($this);
                $exp_gui->addFormat("xml");
                $this->ctrl->forwardCommand($exp_gui);
                break;

            case "ilcommonactiondispatchergui":
                $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                $this->ctrl->forwardCommand($gui);
                break;

            case "ilcertificategui":
                $this->setSettingsSubTabs();
                $this->tabs_gui->activateTab("settings");
                $this->tabs_gui->activateSubTab("certificate");

                $guiFactory = new ilCertificateGUIFactory();
                $output_gui = $guiFactory->create($this->object);

                $this->ctrl->forwardCommand($output_gui);
                break;

            case "ilexassignmenteditorgui":
                $this->checkPermission("write");
                $ilTabs->activateTab("content");
                $this->addContentSubTabs("list_assignments");
                $ass_gui = new ilExAssignmentEditorGUI(
                    $this->object->getId(),
                    $this->object->isCompletionBySubmissionEnabled(),
                    $this->ass
                );
                $this->ctrl->forwardCommand($ass_gui);
                break;

            case "ilexercisemanagementgui":
                // rbac or position access
                if ($GLOBALS['DIC']->access()->checkRbacOrPositionPermissionAccess(
                    'edit_submissions_grades',
                    'edit_submissions_grades',
                    $this->object->getRefId()
                )) {
                    $ilTabs->activateTab("grades");
                    $mgmt_gui = new ilExerciseManagementGUI($this->getService(), $this->ass);
                    $this->ctrl->forwardCommand($mgmt_gui);
                } else {
                    $this->checkPermission("edit_submissions_grades");    // throw error by standard procedure
                }
                break;

            case "ilexccriteriacataloguegui":
                $this->checkPermission("write");
                $ilTabs->activateTab("settings");
                $this->setSettingsSubTabs();
                $ilTabs->activateSubTab("crit");
                $crit_gui = new ilExcCriteriaCatalogueGUI($exc);
                $this->ctrl->forwardCommand($crit_gui);
                break;

                /* seems to be unused, at least initSumbission is not known here...
                case "ilportfolioexercisegui":
                    $this->ctrl->saveParameter($this, array("part_id"));
                    $gui = new ilPortfolioExerciseGUI($this->object, $this->initSubmission());
                    $ilCtrl->forwardCommand($gui);
                    break; */

            case "ilexcrandomassignmentgui":
                $gui = $this->exercise_ui->assignment()->getRandomAssignmentGUI();
                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilobjectmetadatagui':
                $this->checkPermissionBool("write", '', '', $this->object->getRefId());
                $this->tabs_gui->setTabActive('meta_data');
                $md_gui = new ilObjectMetaDataGUI($this->object);
                $this->ctrl->forwardCommand($md_gui);
                break;

            case strtolower(ilAssignmentPresentationGUI::class):
                $this->checkPermission("read");
                $gui = $this->exercise_ui->assignment()->assignmentPresentationGUI($this->object);
                $this->ctrl->forwardCommand($gui);
                break;

            default:
                if (!$cmd) {
                    $cmd = "infoScreen";
                }

                $cmd .= "Object";
                $this->$cmd();

                break;
        }

        $this->addHeaderAction();
    }

    public function viewObject(): void
    {
        $this->infoScreenObject();
    }

    protected function afterSave(ilObject $a_new_object): void
    {
        $ilCtrl = $this->ctrl;

        $a_new_object->saveData();

        $this->tpl->setOnScreenMessage('success', $this->lng->txt("exc_added"), true);

        $ilCtrl->setParameterByClass("ilExAssignmentEditorGUI", "ref_id", $a_new_object->getRefId());
        $ilCtrl->redirectByClass("ilExAssignmentEditorGUI", "addAssignment");
    }

    protected function listAssignmentsObject(): void
    {
        $ilCtrl = $this->ctrl;

        $this->checkPermissionBool("write");

        // #16587
        $ilCtrl->redirectByClass("ilExAssignmentEditorGUI", "listAssignments");
    }

    protected function initEditCustomForm(ilPropertyFormGUI $a_form): void
    {
        $obj_service = $this->getObjectService();
        $service = $this->getService();
        /** @var ilObjExercise $exc */
        $exc = $this->object;

        $random_manager = $service->domain()->assignment()->randomAssignments($exc);

        $a_form->setTitle($this->lng->txt("exc_edit_exercise"));

        $pres = new ilFormSectionHeaderGUI();
        $pres->setTitle($this->lng->txt('obj_presentation'));
        $a_form->addItem($pres);

        // tile image
        $a_form = $obj_service->commonSettings()->legacyForm($a_form, $this->object)->addTileImage();

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt('exc_passing_exc'));
        $a_form->addItem($section);

        // pass mode
        $radg = new ilRadioGroupInputGUI($this->lng->txt("exc_pass_mode"), "pass_mode");

        $op1 = new ilRadioOption(
            $this->lng->txt("exc_pass_all"),
            ilObjExercise::PASS_MODE_ALL,
            $this->lng->txt("exc_pass_all_info")
        );
        $radg->addOption($op1);
        $op2 = new ilRadioOption(
            $this->lng->txt("exc_pass_minimum_nr"),
            ilObjExercise::PASS_MODE_NR,
            $this->lng->txt("exc_pass_minimum_nr_info")
        );
        $radg->addOption($op2);
        $op3 = new ilRadioOption(
            $this->lng->txt("exc_random_selection"),
            ilObjExercise::PASS_MODE_RANDOM,
            $this->lng->txt("exc_random_selection_info")
        );
        if (!$random_manager->canBeActivated() && $this->object->getPassMode() != ilObjExercise::PASS_MODE_RANDOM) {
            $op3->setDisabled(true);
            $op3->setInfo(
                $this->lng->txt("exc_random_selection_not_changeable_info") . " " .
                implode(" ", $random_manager->getDeniedActivationReasons())
            );
        }
        if ($this->object->getPassMode() == ilObjExercise::PASS_MODE_RANDOM && !$random_manager->canBeDeactivated()) {
            $radg->setDisabled(true);
            $radg->setInfo(
                $this->lng->txt("exc_pass_mode_not_changeable_info") . " " .
                implode(" ", $random_manager->getDeniedDeactivationReasons())
            );
        }
        // minimum number of assignments to pass
        $rn = new ilNumberInputGUI($this->lng->txt("exc_nr_random_mand"), "nr_random_mand");
        $rn->setSize(4);
        $rn->setMaxLength(4);
        $rn->setRequired(true);
        $rn->setMinValue(1, false);
        $cnt = ilExAssignment::count($this->object->getId());
        $rn->setMaxValue($cnt, true);
        $op3->addSubItem($rn);

        $radg->addOption($op3);

        // minimum number of assignments to pass
        $ni = new ilNumberInputGUI($this->lng->txt("exc_min_nr"), "pass_nr");
        $ni->setSize(4);
        $ni->setMaxLength(4);
        $ni->setRequired(true);
        $mand = ilExAssignment::countMandatory($this->object->getId());
        $min = max($mand, 1);
        $ni->setMinValue($min, true);
        $ni->setInfo($this->lng->txt("exc_min_nr_info"));
        $op2->addSubItem($ni);

        $a_form->addItem($radg);

        // completion by submission
        $subcompl = new ilRadioGroupInputGUI(
            $this->lng->txt("exc_passed_status_determination"),
            "completion_by_submission"
        );
        $op1 = new ilRadioOption($this->lng->txt("exc_completion_by_tutor"), 0, "");
        $subcompl->addOption($op1);
        $op2 = new ilRadioOption(
            $this->lng->txt("exc_completion_by_submission"),
            1,
            $this->lng->txt("exc_completion_by_submission_info")
        );
        $subcompl->addOption($op2);
        $a_form->addItem($subcompl);

        /*$subcompl = new ilCheckboxInputGUI($this->lng->txt('exc_completion_by_submission'), 'completion_by_submission');
        $subcompl->setInfo($this->lng->txt('exc_completion_by_submission_info'));
        $subcompl->setValue(1);
        $a_form->addItem($subcompl);*/

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt('exc_publishing'));
        $a_form->addItem($section);

        // show submissions
        $cb = new ilCheckboxInputGUI($this->lng->txt("exc_show_submissions"), "show_submissions");
        $cb->setInfo($this->lng->txt("exc_show_submissions_info"));
        $a_form->addItem($cb);

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt('exc_notification'));
        $a_form->addItem($section);

        // submission notifications
        $cbox = new ilCheckboxInputGUI($this->lng->txt("exc_submission_notification"), "notification");
        $cbox->setInfo($this->lng->txt("exc_submission_notification_info"));
        $a_form->addItem($cbox);

        // feedback settings

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt('exc_feedback'));
        $a_form->addItem($section);

        $fdb = new ilCheckboxGroupInputGUI($this->lng->txt("exc_settings_feedback"), "tfeedback");
        $a_form->addItem($fdb);

        $option = new ilCheckboxOption(
            $this->lng->txt("exc_settings_feedback_mail"),
            ilObjExercise::TUTOR_FEEDBACK_MAIL
        );
        $option->setInfo($this->lng->txt("exc_settings_feedback_mail_info"));
        $fdb->addOption($option);
        $option = new ilCheckboxOption(
            $this->lng->txt("exc_settings_feedback_file"),
            ilObjExercise::TUTOR_FEEDBACK_FILE
        );
        $option->setInfo($this->lng->txt("exc_settings_feedback_file_info"));
        $fdb->addOption($option);
        $option = new ilCheckboxOption(
            $this->lng->txt("exc_settings_feedback_text"),
            ilObjExercise::TUTOR_FEEDBACK_TEXT
        );
        $option->setInfo($this->lng->txt("exc_settings_feedback_text_info"));
        $fdb->addOption($option);

        // additional features
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt('obj_features'));
        $a_form->addItem($section);

        $features = [ilObjectServiceSettingsGUI::CUSTOM_METADATA];

        $position_settings = ilOrgUnitGlobalSettings::getInstance()
                                                    ->getObjectPositionSettingsByType($this->object->getType());

        if ($position_settings->isActive()) {
            $features[] = ilObjectServiceSettingsGUI::ORGU_POSITION_ACCESS;
        }

        ilObjectServiceSettingsGUI::initServiceSettingsForm(
            $this->object->getId(),
            $a_form,
            $features
        );
    }

    protected function getEditFormCustomValues(array &$a_values): void
    {
        $ilUser = $this->user;

        $a_values["desc"] = $this->object->getLongDescription();
        $a_values["show_submissions"] = $this->object->getShowSubmissions();
        $a_values["pass_mode"] = $this->object->getPassMode();
        if ($a_values["pass_mode"] == "nr") {
            $a_values["pass_nr"] = $this->object->getPassNr();
        }

        $a_values["nr_random_mand"] = $this->object->getNrMandatoryRandom();

        $a_values["notification"] = ilNotification::hasNotification(
            ilNotification::TYPE_EXERCISE_SUBMISSION,
            $ilUser->getId(),
            $this->object->getId()
        );

        $a_values['completion_by_submission'] = (int) $this->object->isCompletionBySubmissionEnabled();

        $tfeedback = array();
        if ($this->object->hasTutorFeedbackMail()) {
            $tfeedback[] = ilObjExercise::TUTOR_FEEDBACK_MAIL;
        }
        if ($this->object->hasTutorFeedbackText()) {
            $tfeedback[] = ilObjExercise::TUTOR_FEEDBACK_TEXT;
        }
        if ($this->object->hasTutorFeedbackFile()) {
            $tfeedback[] = ilObjExercise::TUTOR_FEEDBACK_FILE;
        }
        $a_values['tfeedback'] = $tfeedback;

        // orgunit position setting enabled
        $a_values['obj_orgunit_positions'] = ilOrgUnitGlobalSettings::getInstance()
                                                                    ->isPositionAccessActiveForObject(
                                                                        $this->object->getId()
                                                                    );

        $a_values['cont_custom_md'] = ilContainer::_lookupContainerSetting(
            $this->object->getId(),
            ilObjectServiceSettingsGUI::CUSTOM_METADATA,
            false
        );
    }

    protected function updateCustom(ilPropertyFormGUI $a_form): void
    {
        $obj_service = $this->getObjectService();

        $ilUser = $this->user;
        $this->object->setShowSubmissions($a_form->getInput("show_submissions"));
        $this->object->setPassMode($a_form->getInput("pass_mode"));
        if ($this->object->getPassMode() == "nr") {
            $this->object->setPassNr($a_form->getInput("pass_nr"));
        }
        if ($this->object->getPassMode() == ilObjExercise::PASS_MODE_RANDOM) {
            $this->object->setNrMandatoryRandom($a_form->getInput("nr_random_mand"));
        }

        $this->object->setCompletionBySubmission($a_form->getInput('completion_by_submission') == 1);

        $feedback = $a_form->getInput("tfeedback");
        $this->object->setTutorFeedback(
            is_array($feedback)
                ? array_sum($feedback)
                : null
        );

        ilNotification::setNotification(
            ilNotification::TYPE_EXERCISE_SUBMISSION,
            $ilUser->getId(),
            $this->object->getId(),
            (bool) $a_form->getInput("notification")
        );

        // tile image
        $obj_service->commonSettings()->legacyForm($a_form, $this->object)->saveTileImage();

        ilObjectServiceSettingsGUI::updateServiceSettingsForm(
            $this->object->getId(),
            $a_form,
            array(
                ilObjectServiceSettingsGUI::ORGU_POSITION_ACCESS,
                ilObjectServiceSettingsGUI::CUSTOM_METADATA
            )
        );
    }

    public function addContentSubTabs(string $a_activate): void
    {
        $ilTabs = $this->tabs;
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $ilTabs->addSubTab(
            "content",
            $lng->txt("view"),
            $ilCtrl->getLinkTarget($this, "showOverview")
        );
        if ($this->checkPermissionBool("write")) {
            $ilTabs->addSubTab(
                "list_assignments",
                $lng->txt("edit"),
                $ilCtrl->getLinkTargetByClass("ilExAssignmentEditorGUI", "listAssignments")
            );
        }
        $ilTabs->activateSubTab($a_activate);
    }

    protected function getTabs(): void
    {
        if ($this->ctrl->getNextClass() === strtolower(ilAssignmentPresentationGUI::class)) {
            return;
        }


        $lng = $this->lng;
        $ilHelp = $this->help;
        /** @var $ctrl ilCtrl */
        $ctrl = $this->ctrl;
        $ilHelp->setScreenIdComponent("exc");

        if ($this->checkPermissionBool("read")) {
            $this->tabs_gui->addTab(
                "content",
                $lng->txt("exc_assignments"),
                $ctrl->getLinkTarget($this, "showOverview")
            );
        }

        if ($this->checkPermissionBool("visible") || $this->checkPermissionBool("read")) {
            $this->tabs_gui->addTab(
                "info",
                $lng->txt("info_short"),
                $this->ctrl->getLinkTargetByClass("ilinfoscreengui", "showSummary")
            );
        }

        // edit properties
        if ($this->checkPermissionBool("write")) {
            /*$tabs_gui->addTab("assignments",
                $lng->txt("exc_edit_assignments"),
                $this->ctrl->getLinkTarget($this, 'listAssignments'));*/

            $this->tabs_gui->addTab(
                "settings",
                $lng->txt("settings"),
                $this->ctrl->getLinkTarget($this, 'edit')
            );
        }
        if ($this->access->checkRbacOrPositionPermissionAccess(
            'edit_submissions_grades',
            'edit_submissions_grades',
            $this->object->getRefId()
        )) {
            $this->tabs_gui->addTab(
                "grades",
                $lng->txt("exc_submissions_and_grades"),
                $this->ctrl->getLinkTargetByClass("ilexercisemanagementgui", "members")
            );
        }

        // learning progress
        $ctrl->clearParametersByClass('illearningprogressgui');

        if (ilLearningProgressAccess::checkAccess($this->object->getRefId())) {
            $this->tabs_gui->addTab(
                'learning_progress',
                $lng->txt('learning_progress'),
                $this->ctrl->getLinkTargetByClass(array('ilobjexercisegui', 'illearningprogressgui'), '')
            );
        }

        // meta data
        if ($this->access->checkAccess('write', '', $this->object->getRefId())) {
            $mdgui = new ilObjectMetaDataGUI($this->object);
            $mdtab = $mdgui->getTab();
            if ($mdtab) {
                $this->tabs_gui->addTarget(
                    "meta_data",
                    $mdtab,
                    "",
                    "ilobjectmetadatagui"
                );
            }
        }

        // export
        if ($this->checkPermissionBool("write")) {
            $this->tabs_gui->addTab(
                "export",
                $lng->txt("export"),
                $this->ctrl->getLinkTargetByClass("ilexportgui", "")
            );
        }

        // permissions
        if ($this->checkPermissionBool("edit_permission")) {
            $this->tabs_gui->addTab(
                'permissions',
                $lng->txt("perm_settings"),
                $this->ctrl->getLinkTargetByClass(array(get_class($this), 'ilpermissiongui'), "perm")
            );
        }
    }

    /**
     * @throws ilObjectException
     */
    public function infoScreenObject(): void
    {
        $this->ctrl->setCmd("showSummary");
        $this->ctrl->setCmdClass("ilinfoscreengui");
        $this->infoScreen();
    }

    protected function getService(): InternalService
    {
        return $this->service;
    }

    /**
     * @throws ilObjectException
     */
    public function infoScreen(): void
    {
        $ilUser = $this->user;
        $ilTabs = $this->tabs;
        $lng = $this->lng;

        $ilTabs->activateTab("info");

        /** @var ilObjExercise $exc */
        $exc = $this->object;

        if (!$this->checkPermissionBool("read")) {
            $this->checkPermission("visible");
        }

        $info = new ilInfoScreenGUI($this);

        $info->enablePrivateNotes();

        $info->enableNews();
        if ($this->checkPermissionBool("write")) {
            $info->enableNewsEditing();
            $info->setBlockProperty("news", "settings", true);
        }

        $record_gui = new ilAdvancedMDRecordGUI(ilAdvancedMDRecordGUI::MODE_INFO, 'exc', $this->object->getId());
        $record_gui->setInfoObject($info);
        $record_gui->parse();

        // standard meta data
        $info->addMetaDataSections($this->object->getId(), 0, $this->object->getType());

        // instructions
        $info->addSection($this->lng->txt("exc_overview"));
        $ass = ilExAssignment::getAssignmentDataOfExercise($this->object->getId());
        $cnt = 0;
        $mcnt = 0;
        foreach ($ass as $a) {
            $cnt++;
            if ($a["mandatory"]) {
                $mcnt++;
            }
        }
        $info->addProperty($lng->txt("exc_assignments"), $cnt);
        if ($this->object->getPassMode() == ilObjExercise::PASS_MODE_ALL) {
            $info->addProperty($lng->txt("exc_mandatory"), $mcnt);
            $info->addProperty(
                $lng->txt("exc_pass_mode"),
                $lng->txt("exc_msg_all_mandatory_ass")
            );
        } elseif ($this->object->getPassMode() == ilObjExercise::PASS_MODE_NR) {
            $info->addProperty($lng->txt("exc_mandatory"), $mcnt);
            $info->addProperty(
                $lng->txt("exc_pass_mode"),
                sprintf($lng->txt("exc_msg_min_number_ass"), $this->object->getPassNr())
            );
        } elseif ($this->object->getPassMode() == ilObjExercise::PASS_MODE_RANDOM) {
            $info->addProperty($lng->txt("exc_mandatory"), $exc->getNrMandatoryRandom());
            $info->addProperty(
                $lng->txt("exc_pass_mode"),
                $lng->txt("exc_msg_all_mandatory_ass")
            );
        }

        // feedback from tutor
        if ($this->checkPermissionBool("read")) {
            $lpcomment = ilLPMarks::_lookupComment($ilUser->getId(), $this->object->getId());
            $mark = ilLPMarks::_lookupMark($ilUser->getId(), $this->object->getId());
            //$status = ilExerciseMembers::_lookupStatus($this->object->getId(), $ilUser->getId());
            $st = $this->object->determinStatusOfUser($ilUser->getId());
            $status = $st["overall_status"];
            if ($lpcomment != "" || $mark != "" || $status != "notgraded") {
                $info->addSection($this->lng->txt("exc_feedback_from_tutor"));
                if ($lpcomment != "") {
                    $info->addProperty(
                        $this->lng->txt("exc_comment"),
                        $lpcomment
                    );
                }
                if ($mark != "") {
                    $info->addProperty(
                        $this->lng->txt("exc_mark"),
                        $mark
                    );
                }

                //if ($status == "")
                //{
                //  $info->addProperty($this->lng->txt("status"),
                //		$this->lng->txt("message_no_delivered_files"));
                //}
                //else
                if ($status != "notgraded") {
                    $icons = ilLPStatusIcons::getInstance(ilLPStatusIcons::ICON_VARIANT_LONG);

                    switch ($status) {
                        case "passed":
                            $path = $icons->getImagePathCompleted();
                            break;
                        case "failed":
                            $path = $icons->getImagePathFailed();
                            break;
                        default:
                            $path = ilUtil::getImagePath("scorm/" . $status . ".svg");
                    }

                    $img = $icons->renderIcon($path, $lng->txt("exc_" . $status));

                    $add = "";
                    if ($st["failed_a_mandatory"]) {
                        $add = " (" . $lng->txt("exc_msg_failed_mandatory") . ")";
                    } elseif ($status == "failed") {
                        $add = " (" . $lng->txt("exc_msg_missed_minimum_number") . ")";
                    }
                    $info->addProperty(
                        $this->lng->txt("status"),
                        $img . " " . $this->lng->txt("exc_" . $status) . $add
                    );
                }
            }
        }

        // forward the command
        $this->ctrl->forwardCommand($info);
    }

    public function editObject(): void
    {
        $this->setSettingsSubTabs();
        $this->tabs_gui->activateSubTab("edit");
        parent::editObject();
    }

    protected function setSettingsSubTabs(): void
    {
        $this->tabs_gui->addSubTab(
            "edit",
            $this->lng->txt("general_settings"),
            $this->ctrl->getLinkTarget($this, "edit")
        );

        $this->tabs_gui->addSubTab(
            "crit",
            $this->lng->txt("exc_criteria_catalogues"),
            $this->ctrl->getLinkTargetByClass("ilexccriteriacataloguegui", "")
        );

        $validator = new ilCertificateActiveValidator();
        if ($validator->validate()) {
            $this->tabs_gui->addSubTab(
                "certificate",
                $this->lng->txt("certificate"),
                $this->ctrl->getLinkTarget($this, "certificate")
            );
        }
    }

    public static function _goto(
        string $a_target,
        string $a_raw
    ): void {
        global $DIC;

        $DIC->exercise()->internal()->gui()->permanentLink()->goto($a_target, $a_raw);
    }

    /**
     * Add locator item
     */
    protected function addLocatorItems(): void
    {
        $ilLocator = $this->locator;

        if (is_object($this->object)) {
            // #17955
            $ilLocator->addItem(
                $this->object->getTitle(),
                $this->ctrl->getLinkTarget($this, "showOverview"),
                "",
                $this->requested_ref_id
            );
        }
    }


    ////
    //// Assignments, Learner's View
    ////

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilObjectException
     * @throws ilExcUnknownAssignmentTypeException
     * @throws ilDateTimeException
     */
    public function showOverviewObject(): void
    {
        $this->ctrl->setParameterByClass(self::class, "from_overview", "1");
        $user = $this->service->domain()->user();
        $toolbar = $this->service->gui()->toolbar();
        $tabs = $this->service->gui()->tabs();

        $this->checkPermission("read");

        $tabs->activateTab("content");
        $this->addContentSubTabs("list");

        if ($this->handleRandomAssignmentEntryPage()) {
            return;
        }

        //$tpl->addJavaScript("./Modules/Exercise/js/ilExcPresentation.js");

        $exc = $this->object;

        ilLearningProgress::_tracProgress(
            $user->getId(),
            $exc->getId(),
            $exc->getRefId(),
            'exc'
        );

        if ($this->certificateDownloadValidator->isCertificateDownloadable(
            $user->getId(),
            $exc->getId()
        )) {
            $toolbar->addButton(
                $this->lng->txt("certificate"),
                $this->ctrl->getLinkTarget($this, "outCertificate")
            );
        }

        $ass_gui = new ilExAssignmentGUI($exc, $this->getService());

        $f = $this->ui->factory();
        $r = $this->ui->renderer();

        $ass_data = ilExAssignment::getInstancesByExercise($exc->getId());
        $random_manager = $this->service->domain()->assignment()->randomAssignments($exc);
        $am = $this->ass_manager;
        if ($this->getCurrentMode() === $am::TYPE_ALL) {
            $list_modes = [$am::TYPE_ONGOING,$am::TYPE_FUTURE,$am::TYPE_PAST];
        } else {
            $list_modes = [$this->getCurrentMode()];
        }
        foreach ($list_modes as $lm) {
            $items[$lm] = [];
            foreach ($this->ass_manager->getList($lm) as $ass) {
                if (!$random_manager->isAssignmentVisible($ass->getId(), $this->user->getId())) {
                    continue;
                }
                $items[$lm][] = $this->item_builder->getItem($ass, $user->getId());
            }
        }

        // new
        $groups = [];
        foreach ($items as $lm => $it) {
            if (count($it) > 0) {
                $groups[] = $f->item()->group($this->lng->txt("exc_" . $lm), $it);
            }
        }
        if (count($groups) > 0) {
            $panel = $f->panel()->listing()->standard($this->lng->txt("exc_assignments"), $groups);
        } else {
            $panel = $f->panel()->standard($this->lng->txt("exc_assignments"), $f->messageBox()->info($this->lng->txt("exc_no_assignments")));
        }

        $mode_options = [];
        foreach ($am->getListModes() as $mode => $txt) {
            $mode_options[$txt] = $this->getModeLink($mode);
        }
        $mode = $f->viewControl()->mode(
            $mode_options,
            $this->lng->txt("exc_mode_selection")
        )->withActive($am->getListModeLabel($this->getCurrentMode()));

        $html = "";
        $l = $f->legacy("<br><br>");
        $html .= $r->render([$mode, $l, $panel]);

        $this->tpl->setContent(
            $html
        );
        $this->ctrl->setParameterByClass(self::class, "from_overview", null);
    }

    protected function getCurrentMode(): string
    {
        return $this->ass_manager->getValidListMode($this->exercise_request->getMode());
    }

    protected function getModeLink(string $mode): string
    {
        $this->ctrl->setParameterByClass(self::class, "mode", $mode);
        $link = $this->ctrl->getLinkTargetByClass(self::class, "showOverview");
        $this->ctrl->setParameterByClass(self::class, "mode", null);
        return $link;
    }

    /**
     * @throws ilException
     */
    public function certificateObject(): void
    {
        $this->setSettingsSubTabs();
        $this->tabs_gui->activateTab("settings");
        $this->tabs_gui->activateSubTab("certificate");

        $guiFactory = new ilCertificateGUIFactory();
        $output_gui = $guiFactory->create($this->object);

        $output_gui->certificateEditor();
    }

    public function outCertificateObject(): void
    {
        global $DIC;

        $database = $DIC->database();
        $logger = $DIC->logger()->root();

        $ilUser = $this->user;

        $objectId = $this->object->getId();

        if (!$this->certificateDownloadValidator->isCertificateDownloadable($ilUser->getId(), $objectId)) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->redirect($this);
        }

        $ilUserCertificateRepository = new ilUserCertificateRepository($database, $logger);
        $pdfGenerator = new ilPdfGenerator($ilUserCertificateRepository);

        $pdfAction = new ilCertificatePdfAction(
            $pdfGenerator,
            new ilCertificateUtilHelper(),
            $this->lng->txt('error_creating_certificate_pdf')
        );

        $pdfAction->downloadPdf($ilUser->getId(), $objectId);
    }

    /**
     * Start assignment with relative deadline
     */
    public function startAssignmentObject(): void
    {
        $ctrl = $this->ctrl;
        $user = $this->user;

        if ($this->ass !== null) {
            $state = ilExcAssMemberState::getInstanceByIds($this->ass->getId(), $user->getId());
            if (!$state->getCommonDeadline() && $state->getRelativeDeadline()) {
                $idl = $state->getIndividualDeadlineObject();
                $idl->setStartingTimestamp(time());
                $idl->save();
            }
        }

        $ctrl->setParameterByClass(ilAssignmentPresentationGUI::class, "ass_id", $this->ass->getId());
        $ctrl->redirectByClass(ilAssignmentPresentationGUI::class, "");
    }

    /**
     * Request deadline for assignment with absolute individual deadline only
     */
    public function requestDeadlineObject(): void
    {
        $ctrl = $this->ctrl;
        $user = $this->user;

        if ($this->ass !== null) {
            $state = ilExcAssMemberState::getInstanceByIds($this->ass->getId(), $user->getId());
            if ($state->needsIndividualDeadline() && !$state->hasRequestedIndividualDeadline()) {
                $idl = $state->getIndividualDeadlineObject();
                $idl->setRequested(true);
                $idl->save();
                $this->notification->sendDeadlineRequestNotification($this->ass->getId());
                /** @var ilObjExercise $exc */
                $exc = $this->object;
                $exc->members_obj->assignMembers([$user->getId()]);
            }
        }

        $ctrl->setParameterByClass(ilAssignmentPresentationGUI::class, "ass_id", $this->ass->getId());
        $ctrl->redirectByClass(ilAssignmentPresentationGUI::class, "");
    }

    /**
     * Display random assignment start page, if necessary
     */
    protected function handleRandomAssignmentEntryPage(): bool
    {
        /** @var ilObjExercise $exc */
        $exc = $this->object;

        $service = $this->getService();
        $random_manager = $service->domain()->assignment()->randomAssignments($exc);
        if ($random_manager->needsStart()) {
            $gui = $this->exercise_ui->assignment()->getRandomAssignmentGUI();
            $gui->renderStartPage();
            return true;
        }

        return false;
    }
}
