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

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\Cron\Schedule\CronJobScheduleType;

/**
 * @ilCtrl_Calls ilCronManagerGUI: ilPropertyFormGUI
 * @ilCtrl_isCalledBy ilCronManagerGUI: ilAdministrationGUI
 */
class ilCronManagerGUI
{
    private const FORM_PARAM_SCHEDULE_PREFIX = 'schedule_';
    public const FORM_PARAM_MAIN_SECTION = 'main';
    public const FORM_PARAM_JOB_INPUT = 'additional_job_input';
    public const FORM_PARAM_GROUP_SCHEDULE = 'schedule';

    private readonly ilLanguage $lng;
    private readonly ilCtrlInterface $ctrl;
    private readonly ilSetting $settings;
    private readonly ilGlobalTemplateInterface $tpl;
    private readonly Factory $ui_factory;
    private readonly Renderer $ui_renderer;
    private readonly ilUIService $ui_service;
    private readonly ilCronJobRepository $cron_repository;
    private readonly \ILIAS\DI\RBACServices $rbac;
    private readonly ilErrorHandling $error;
    private readonly WrapperFactory $http_wrapper;
    private readonly \ILIAS\HTTP\GlobalHttpState $http_service;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly ilCronManager $cron_manager;
    private readonly ilObjUser $actor;

    public function __construct()
    {
        /** @var $DIC \ILIAS\DI\Container */
        global $DIC;

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->settings = $DIC->settings();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->ui_service = $DIC->uiService();
        $this->rbac = $DIC->rbac();
        $this->error = $DIC['ilErr'];
        $this->http_wrapper = $DIC->http()->wrapper();
        $this->http_service = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->actor = $DIC->user();
        $this->cron_repository = $DIC->cron()->repository();
        $this->cron_manager = $DIC->cron()->manager();

        $this->lng->loadLanguageModule('cron');
        $this->lng->loadLanguageModule('cmps');
    }

    /**
     * @param mixed $default
     * @return mixed|null
     */
    protected function getRequestValue(
        string $key,
        \ILIAS\Refinery\Transformation $trafo,
        bool $forceRetrieval = false,
        $default = null
    ) {
        $exc = null;

        try {
            if ($forceRetrieval || $this->http_wrapper->query()->has($key)) {
                return $this->http_wrapper->query()->retrieve($key, $trafo);
            }
        } catch (OutOfBoundsException $e) {
            $exc = $e;
        }

        try {
            if ($forceRetrieval || $this->http_wrapper->post()->has($key)) {
                return $this->http_wrapper->post()->retrieve($key, $trafo);
            }
        } catch (OutOfBoundsException $e) {
            $exc = $e;
        }

        if ($forceRetrieval && $exc) {
            throw $exc;
        }

        return $default ?? null;
    }

    public function executeCommand(): void
    {
        if (!$this->rbac->system()->checkAccess('visible,read', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $class = $this->ctrl->getNextClass($this);

        /** @noinspection PhpSwitchStatementWitSingleBranchInspection */
        switch (strtolower($class)) {
            case strtolower(ilPropertyFormGUI::class):
                $job_id = $this->getRequestValue('jid', $this->refinery->kindlyTo()->string());
                $job = $this->cron_repository->getJobInstanceById(ilUtil::stripSlashes($job_id));
                $form = $this->initLegacyEditForm($job);
                $this->ctrl->forwardCommand($form);
                break;
        }

        $cmd = $this->ctrl->getCmd('render');
        $this->$cmd();
    }

    protected function render(): void
    {
        $tstamp = $this->lng->txt('cronjob_last_start_unknown');
        if ($this->settings->get('last_cronjob_start_ts')) {
            $tstamp = ilDatePresentation::formatDate(
                new ilDateTime(
                    $this->settings->get('last_cronjob_start_ts'),
                    IL_CAL_UNIX
                )
            );
        }

        $message = $this->ui_factory->messageBox()->info($this->lng->txt('cronjob_last_start') . ': ' . $tstamp);

        $cronJobs = $this->cron_repository->findAll();

        $tableFilterMediator = new ilCronManagerTableFilterMediator(
            $cronJobs,
            $this->ui_factory,
            $this->ui_service,
            $this->lng
        );
        $filter = $tableFilterMediator->filter($this->ctrl->getFormAction(
            $this,
            'render',
            '',
            true
        ));

        $tbl = new ilCronManagerTableGUI(
            $this,
            $this->cron_repository,
            'render',
            $this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)
        );
        $this->tpl->setContent(implode('', [
            $this->ui_renderer->render([$message, $filter]),
            $tbl->populate(
                $tableFilterMediator->filteredJobs(
                    $filter
                )
            )->getHTML()
        ]));
    }

    public function edit(?ILIAS\UI\Component\Input\Container\Form\Form $form = null): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $job_id = $this->getRequestValue('jid', $this->refinery->kindlyTo()->string());
        if (!$job_id) {
            $this->ctrl->redirect($this, 'render');
        }

        if ($form === null) {
            $job = $this->cron_repository->getJobInstanceById($job_id);
            if ($job && $job->usesLegacyForms()) {
                $this->ctrl->setParameter($this, 'jid', $job->getId());
                $this->ctrl->redirect($this, 'editLegacy');
            }

            $form = $this->initEditForm($job);
        }

        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    public function editLegacy(?ilPropertyFormGUI $a_form = null): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $job_id = $this->getRequestValue('jid', $this->refinery->kindlyTo()->string());
        if (!$job_id) {
            $this->ctrl->redirect($this, 'render');
        }

        if ($a_form === null) {
            $job = $this->cron_repository->getJobInstanceById($job_id);
            $a_form = $this->initLegacyEditForm($job);
        }

        $this->tpl->setContent($a_form->getHTML());
    }

    private function getScheduleTypeFormElementName(CronJobScheduleType $schedule_type): string
    {
        return match ($schedule_type) {
            CronJobScheduleType::SCHEDULE_TYPE_DAILY => $this->lng->txt('cron_schedule_daily'),
            CronJobScheduleType::SCHEDULE_TYPE_WEEKLY => $this->lng->txt('cron_schedule_weekly'),
            CronJobScheduleType::SCHEDULE_TYPE_MONTHLY => $this->lng->txt('cron_schedule_monthly'),
            CronJobScheduleType::SCHEDULE_TYPE_QUARTERLY => $this->lng->txt('cron_schedule_quarterly'),
            CronJobScheduleType::SCHEDULE_TYPE_YEARLY => $this->lng->txt('cron_schedule_yearly'),
            CronJobScheduleType::SCHEDULE_TYPE_IN_MINUTES => sprintf($this->lng->txt('cron_schedule_in_minutes'), 'x'),
            CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS => sprintf($this->lng->txt('cron_schedule_in_hours'), 'x'),
            CronJobScheduleType::SCHEDULE_TYPE_IN_DAYS => sprintf($this->lng->txt('cron_schedule_in_days'), 'x'),
        };
    }

    protected function getScheduleValueFormElementName(CronJobScheduleType $schedule_type): string
    {
        return match ($schedule_type) {
            CronJobScheduleType::SCHEDULE_TYPE_IN_MINUTES => 'smini',
            CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS => 'shri',
            CronJobScheduleType::SCHEDULE_TYPE_IN_DAYS => 'sdyi',
            default => throw new InvalidArgumentException(sprintf(
                'The passed argument %s is invalid!',
                var_export($schedule_type, true)
            )),
        };
    }

    protected function hasScheduleValue(CronJobScheduleType $schedule_type): bool
    {
        return in_array($schedule_type, [
            CronJobScheduleType::SCHEDULE_TYPE_IN_MINUTES,
            CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS,
            CronJobScheduleType::SCHEDULE_TYPE_IN_DAYS
        ], true);
    }

    protected function initEditForm(?ilCronJob $job): ILIAS\UI\Component\Input\Container\Form\Form
    {
        if (!($job instanceof ilCronJob)) {
            $this->ctrl->redirect($this, 'render');
        }

        $this->ctrl->setParameter($this, 'jid', $job->getId());

        $jobs_data = $this->cron_repository->getCronJobData($job->getId());
        $job_data = $jobs_data[0];

        $section_inputs = [];
        if ($job->hasFlexibleSchedule()) {
            $schedule_type_groups = [];
            foreach ($job->getAllScheduleTypes() as $schedule_type) {
                if (!in_array($schedule_type, $job->getValidScheduleTypes(), true)) {
                    continue;
                }

                $schedule_type_inputs = [];
                if (in_array($schedule_type, $job->getScheduleTypesWithValues(), true)) {
                    $schedule_value_input = $this->ui_factory
                        ->input()
                        ->field()
                        ->numeric(
                            $this->lng->txt('cron_schedule_value')
                        )->withAdditionalTransformation(
                            $this->refinery->in()->series([
                                $this->refinery->int()->isGreaterThanOrEqual(1)
                            ])
                        )->withRequired(true);

                    if (is_numeric($job_data['schedule_type']) &&
                        CronJobScheduleType::tryFrom((int) $job_data['schedule_type']) === $schedule_type) {
                        $schedule_value_input = $schedule_value_input->withValue(
                            $job_data['schedule_value'] === null ? null : (int) $job_data['schedule_value']
                        );
                    }

                    $schedule_type_inputs = [
                        $this->getScheduleValueFormElementName($schedule_type) => $schedule_value_input
                    ];
                }

                $schedule_type_groups[self::FORM_PARAM_SCHEDULE_PREFIX . $schedule_type->value] = $this->ui_factory
                    ->input()
                    ->field()
                    ->group(
                        $schedule_type_inputs,
                        $this->getScheduleTypeFormElementName($schedule_type)
                    )
                    ->withDedicatedName(self::FORM_PARAM_SCHEDULE_PREFIX . $schedule_type->value);
            }

            $default_schedule_type = current($job->getValidScheduleTypes())->value;

            $section_inputs['schedule'] = $this->ui_factory
                ->input()
                ->field()
                ->switchableGroup(
                    $schedule_type_groups,
                    $this->lng->txt('cron_schedule_type')
                )
                ->withRequired(true)
                ->withValue(
                    $job_data['schedule_type'] === null ?
                        self::FORM_PARAM_SCHEDULE_PREFIX . $default_schedule_type :
                        self::FORM_PARAM_SCHEDULE_PREFIX . $job_data['schedule_type']
                );
        }

        $main_section = $this->ui_factory->input()->field()->section(
            $section_inputs,
            $this->lng->txt('cron_action_edit') . ': "' . $job->getTitle() . '"'
        );

        $inputs = [
            self::FORM_PARAM_MAIN_SECTION => $main_section
        ];

        if ($job->hasCustomSettings()) {
            $inputs = array_merge(
                $inputs,
                [
                    self::FORM_PARAM_JOB_INPUT =>
                    $job->getCustomConfigurationInput(
                        $this->ui_factory,
                        $this->refinery,
                        $this->lng
                    )
                ]
            );
        }

        return $this->ui_factory
            ->input()
            ->container()
            ->form()
            ->standard($this->ctrl->getFormAction($this, 'update'), $inputs)
            ->withDedicatedName('cron_form');
    }

    /**
     * @deprecated
     */
    #[\Deprecated('Will be removed without any alternative, KS/UI forms will be expected', since: '13.0')]
    protected function initLegacyEditForm(?ilCronJob $job): ilPropertyFormGUI
    {
        if (!($job instanceof ilCronJob)) {
            $this->ctrl->redirect($this, 'render');
        }

        $this->ctrl->setParameter($this, 'jid', $job->getId());

        $jobs_data = $this->cron_repository->getCronJobData($job->getId());
        $job_data = $jobs_data[0];

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'updateLegacy'));
        $form->setTitle($this->lng->txt('cron_action_edit') . ': "' . $job->getTitle() . '"');

        if ($job->hasFlexibleSchedule()) {
            $type = new ilRadioGroupInputGUI($this->lng->txt('cron_schedule_type'), 'type');
            $type->setRequired(true);
            $type->setValue($job_data['schedule_type'] === null ? null : (string) $job_data['schedule_type']);

            foreach ($job->getAllScheduleTypes() as $schedule_type) {
                if (!in_array($schedule_type, $job->getValidScheduleTypes(), true)) {
                    continue;
                }

                $option = new ilRadioOption(
                    $this->getScheduleTypeFormElementName($schedule_type),
                    (string) $schedule_type->value
                );
                $type->addOption($option);

                if (in_array($schedule_type, $job->getScheduleTypesWithValues(), true)) {
                    $scheduleValue = new ilNumberInputGUI(
                        $this->lng->txt('cron_schedule_value'),
                        $this->getScheduleValueFormElementName($schedule_type)
                    );
                    $scheduleValue->allowDecimals(false);
                    $scheduleValue->setRequired(true);
                    $scheduleValue->setSize(5);
                    if (is_numeric($job_data['schedule_type']) &&
                        CronJobScheduleType::tryFrom((int) $job_data['schedule_type']) === $schedule_type) {
                        $scheduleValue->setValue($job_data['schedule_value'] === null ? null : (string) $job_data['schedule_value']);
                    }
                    $option->addSubItem($scheduleValue);
                }
            }

            $form->addItem($type);
        }

        if ($job->hasCustomSettings()) {
            $job->addCustomSettingsToForm($form);
        }

        $form->addCommandButton('updateLegacy', $this->lng->txt('save'));
        $form->addCommandButton('render', $this->lng->txt('cancel'));

        return $form;
    }

    public function update(): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $job_id = $this->getRequestValue('jid', $this->refinery->kindlyTo()->string());
        if (!$job_id) {
            $this->ctrl->redirect($this, 'render');
        }

        $job = $this->cron_repository->getJobInstanceById($job_id);
        $form = $this->initEditForm($job);

        $form_valid = false;
        $form_data = null;
        if ($this->http_service->request()->getMethod() === 'POST') {
            $form = $form->withRequest($this->http_service->request());
            $form_data = $form->getData();
            $form_valid = $form_data !== null;
        }

        if (!$form_valid) {
            $this->edit($form);
            return;
        }

        if ($job instanceof ilCronJob) {
            if ($job->hasFlexibleSchedule()) {
                $schedule_group = $form_data[self::FORM_PARAM_MAIN_SECTION][self::FORM_PARAM_GROUP_SCHEDULE];

                $type = CronJobScheduleType::from(
                    (int) ltrim($schedule_group[0], self::FORM_PARAM_SCHEDULE_PREFIX)
                );

                $value = match (true) {
                    $this->hasScheduleValue($type) => (int) $schedule_group[1][$this->getScheduleValueFormElementName($type)],
                    default => null,
                };

                $this->cron_repository->updateJobSchedule($job, $type, $value);
            }

            if ($job->hasCustomSettings()) {
                $job->saveCustomConfiguration($form_data[self::FORM_PARAM_JOB_INPUT]);
            }

            $this->tpl->setOnScreenMessage('success', $this->lng->txt('cron_action_edit_success'), true);
            $this->ctrl->redirect($this, 'render');
        }

        $this->edit($form);
    }

    /**
     * @deprecated
     */
    #[\Deprecated('Will be removed without any alternative, KS/UI forms will be expected', since: '12.0')]
    public function updateLegacy(): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $job_id = $this->getRequestValue('jid', $this->refinery->kindlyTo()->string());
        if (!$job_id) {
            $this->ctrl->redirect($this, 'render');
        }

        $job = $this->cron_repository->getJobInstanceById($job_id);

        $form = $this->initLegacyEditForm($job);
        if ($job instanceof ilCronJob && $form->checkInput()) {
            $valid = true;
            if ($job->hasCustomSettings() && !$job->saveCustomSettings($form)) {
                $valid = false;
            }

            if ($valid && $job->hasFlexibleSchedule()) {
                $type = CronJobScheduleType::from((int) $form->getInput('type'));
                $value = match (true) {
                    $this->hasScheduleValue($type) => (int) $form->getInput($this->getScheduleValueFormElementName($type)),
                    default => null,
                };

                $this->cron_repository->updateJobSchedule($job, $type, $value);
            }

            if ($valid) {
                $this->tpl->setOnScreenMessage('success', $this->lng->txt('cron_action_edit_success'), true);
                $this->ctrl->redirect($this, 'render');
            }
        }

        $form->setValuesByPost();
        $this->editLegacy($form);
    }

    public function run(): void
    {
        $this->confirm('run');
    }

    public function confirmedRun(): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $job_id = $this->getRequestValue('jid', $this->refinery->kindlyTo()->string());
        if ($job_id) {
            if ($this->cron_manager->runJobManual($job_id, $this->actor)) {
                $this->tpl->setOnScreenMessage('success', $this->lng->txt('cron_action_run_success'), true);
            } else {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('cron_action_run_fail'), true);
            }
        }

        $this->ctrl->redirect($this, 'render');
    }

    public function activate(): void
    {
        $this->confirm('activate');
    }

    public function confirmedActivate(): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $jobs = $this->getMultiActionData();
        if ($jobs !== []) {
            foreach ($jobs as $job) {
                if ($this->cron_manager->isJobInactive($job->getId())) {
                    $this->cron_manager->resetJob($job, $this->actor);
                }
            }

            $this->tpl->setOnScreenMessage('success', $this->lng->txt('cron_action_activate_success'), true);
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('no_checkbox'), true);
        }

        $this->ctrl->redirect($this, 'render');
    }

    public function deactivate(): void
    {
        $this->confirm('deactivate');
    }

    public function confirmedDeactivate(): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $jobs = $this->getMultiActionData();
        if ($jobs !== []) {
            foreach ($jobs as $job) {
                if ($this->cron_manager->isJobActive($job->getId())) {
                    $this->cron_manager->deactivateJob($job, $this->actor, true);
                }
            }

            $this->tpl->setOnScreenMessage('success', $this->lng->txt('cron_action_deactivate_success'), true);
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('no_checkbox'), true);
        }

        $this->ctrl->redirect($this, 'render');
    }

    public function reset(): void
    {
        $this->confirm('reset');
    }

    public function confirmedReset(): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $jobs = $this->getMultiActionData();
        if ($jobs !== []) {
            foreach ($jobs as $job) {
                $this->cron_manager->resetJob($job, $this->actor);
            }
            $this->tpl->setOnScreenMessage('success', $this->lng->txt('cron_action_reset_success'), true);
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('no_checkbox'), true);
        }

        $this->ctrl->redirect($this, 'render');
    }

    /**
     * @return array<string, ilCronJob>
     */
    protected function getMultiActionData(): array
    {
        $res = [];

        $job_ids = [];
        try {
            try {
                $job_ids = [$this->getRequestValue('jid', $this->refinery->kindlyTo()->string(), true)];
            } catch (\ILIAS\Refinery\ConstraintViolationException | OutOfBoundsException) {
                $job_ids = $this->getRequestValue('mjid', $this->refinery->kindlyTo()->listOf(
                    $this->refinery->kindlyTo()->string()
                ), false, []);
            }
        } catch (\ILIAS\Refinery\ConstraintViolationException | OutOfBoundsException) {
        }

        foreach ($job_ids as $job_id) {
            $job = $this->cron_repository->getJobInstanceById($job_id);
            if ($job instanceof ilCronJob) {
                $res[$job_id] = $job;
            }
        }

        return $res;
    }

    protected function confirm(string $a_action): void
    {
        if (!$this->rbac->system()->checkAccess('write', SYSTEM_FOLDER_ID)) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $jobs = $this->getMultiActionData();
        if ($jobs === []) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('no_checkbox'), true);
            $this->ctrl->redirect($this, 'render');
        }

        if ('run' === $a_action) {
            $jobs = array_filter($jobs, static function (ilCronJob $job): bool {
                return $job->isManuallyExecutable();
            });

            if ($jobs === []) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('cron_no_executable_job_selected'), true);
                $this->ctrl->redirect($this, 'render');
            }
        }

        $cgui = new ilConfirmationGUI();

        if (1 === count($jobs)) {
            $jobKeys = array_keys($jobs);
            $job_id = array_pop($jobKeys);
            $job = array_pop($jobs);
            $title = $job->getTitle();
            if (!$title) {
                $title = preg_replace('[^A-Za-z0-9_\-]', '', $job->getId());
            }

            $cgui->setHeaderText(sprintf(
                $this->lng->txt('cron_action_' . $a_action . '_sure'),
                $title
            ));

            $this->ctrl->setParameter($this, 'jid', $job_id);
        } else {
            $cgui->setHeaderText($this->lng->txt('cron_action_' . $a_action . '_sure_multi'));

            foreach ($jobs as $job_id => $job) {
                $cgui->addItem('mjid[]', $job_id, $job->getTitle());
            }
        }

        $cgui->setFormAction($this->ctrl->getFormAction($this, 'confirmed' . ucfirst($a_action)));
        $cgui->setCancel($this->lng->txt('cancel'), 'render');
        $cgui->setConfirm($this->lng->txt('cron_action_' . $a_action), 'confirmed' . ucfirst($a_action));

        $this->tpl->setContent($cgui->getHTML());
    }

    public function addToExternalSettingsForm(int $a_form_id): array
    {
        $form_elements = [];
        $fields = [];
        $data = $this->cron_repository->getCronJobData();
        foreach ($data as $item) {
            $job = $this->cron_repository->getJobInstance(
                $item['job_id'],
                $item['component'],
                $item['class']
            );
            if (!is_null($job)) {
                $job->addToExternalSettingsForm($a_form_id, $fields, (bool) $item['job_status']);
            }
        }

        if ($fields !== []) {
            $form_elements = [
                'cron_jobs' => [
                    'jumpToCronJobs',
                    $fields
                ]
            ];
        }

        return $form_elements;
    }
}
