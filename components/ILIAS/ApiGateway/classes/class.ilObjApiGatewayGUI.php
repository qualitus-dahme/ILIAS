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

use ILIAS\ApiGateway\Configuration\Admin\ilApiGatewaySettings;
use ILIAS\ApiGateway\Configuration\Admin\UI\Form\GeneralSettings;
use ILIAS\ApiGateway\Configuration\Admin\UI\Form\RestSettings;
use ILIAS\ApiGateway\Configuration\Admin\UI\Form\SettingsForm;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

/**
 * @ilCtrl_isCalledBy ilObjApiGatewayGUI: ilAdministrationGUI
 * @ilCtrl_Calls      ilObjApiGatewayGUI: ilPermissionGUI
 */
final class ilObjApiGatewayGUI extends ilObjectGUI
{
    private const string KEYS_SEPARATOR = '_';
    private const string ACTION_VIEW = 'view';
    private const string ACTION_SAVE = 'save';
    private const string SECTION_PERMISSIONS = 'permissions';
    private const string SECTION_SETTINGS = 'settings';
    private const string SECTION_TAB_GENERAL = 'general';
    private const string SECTION_TAB_REST = 'rest';
    private const string SECTION_TAB_HELP = 'help';

    /** @var array<string, string> */
    private const array ALLOWED_COMMANDS = [
        self::CMD_SETTINGS_GENERAL_VIEW => 'showGeneralSettings',
        self::CMD_SETTINGS_GENERAL_SAVE => 'updateGeneralSettings',
        self::CMD_SETTINGS_REST_VIEW => 'showRestSettings',
        self::CMD_SETTINGS_REST_SAVE => 'updateRestSettings',
        self::CMD_SETTINGS_HELP_VIEW => 'showSettingsHelp',
    ];
    /** @var array<string, string> */
    private const array TAB_FORMS = [
        self::TAB_SETTINGS_GENERAL => GeneralSettings::class,
        self::TAB_SETTINGS_REST => RestSettings::class,
    ];
    private const string TAB_SETTINGS_GENERAL = self::SECTION_SETTINGS . self::KEYS_SEPARATOR . self::SECTION_TAB_GENERAL;
    private const string TAB_SETTINGS_REST = self::SECTION_SETTINGS . self::KEYS_SEPARATOR . self::SECTION_TAB_REST;
    private const string TAB_SETTINGS_HELP = self::SECTION_SETTINGS . self::KEYS_SEPARATOR . self::SECTION_TAB_HELP;
    private const string CMD_SETTINGS_HELP_VIEW = self::ACTION_VIEW . self::KEYS_SEPARATOR . self::SECTION_TAB_HELP . self::KEYS_SEPARATOR . self::SECTION_SETTINGS;
    private const string CMD_SETTINGS_GENERAL_VIEW = self::ACTION_VIEW . self::KEYS_SEPARATOR . self::SECTION_TAB_GENERAL . self::KEYS_SEPARATOR . self::SECTION_SETTINGS;
    private const string CMD_SETTINGS_GENERAL_SAVE = self::ACTION_SAVE . self::KEYS_SEPARATOR . self::SECTION_TAB_GENERAL . self::KEYS_SEPARATOR . self::SECTION_SETTINGS;
    private const string CMD_SETTINGS_REST_VIEW = self::ACTION_VIEW . self::KEYS_SEPARATOR . self::SECTION_TAB_REST . self::KEYS_SEPARATOR . self::SECTION_SETTINGS;
    private const string CMD_SETTINGS_REST_SAVE = self::ACTION_SAVE . self::KEYS_SEPARATOR . self::SECTION_TAB_REST . self::KEYS_SEPARATOR . self::SECTION_SETTINGS;
    private const string DEFAULT_CMD = self::CMD_SETTINGS_GENERAL_VIEW;

    private ilApiGatewaySettings $settings_service;
    private bool $has_read_access = false;
    private bool $has_write_access = false;

    public function __construct($data, int $id = 0, bool $call_by_reference = true, bool $prepare_output = true)
    {
        parent::__construct($data, $id, $call_by_reference, $prepare_output);

        $this->lng->loadLanguageModule(ilObjApiGateway::TYPE);

        $this->settings_service = ilApiGatewaySettings::getInstance();

        $ref_id = $this->object->getRefId();

        $this->has_write_access = $this->access->checkAccess('write', "", $ref_id);
        $this->has_read_access = $this->access->checkAccess('read', "", $ref_id);
    }

    /**
     * Set the Title and the description
     * (Overwritten from ilObjectGUI, called by prepareOutput)
     */
    protected function setTitleAndDescription(): void
    {
        $this->tpl->setTitle($this->lng->txt('webservices'));
        $this->tpl->setTitleIcon('assets/images/standard/icon_wbrs.svg');
        $this->tpl->setDescription($this->lng->txt('webservices_description'));
    }

    public function executeCommand(): void
    {
        if (!$this->has_read_access) {
            $this->ilias->raiseError(
                $this->lng->txt('permission_denied'),
                $this->ilias->error_obj->MESSAGE
            );
        }

        $this->prepareOutput();

        $next_class = $this->ctrl->getNextClass($this);

        switch ($next_class) {
            case strtolower(ilPermissionGUI::class):
                $this->ctrl->forwardCommand(new ilPermissionGUI($this));
                $this->tabs_gui->activateTab(self::SECTION_PERMISSIONS);
                break;
            default:
                // set defaults
                $method = self::ALLOWED_COMMANDS[self::DEFAULT_CMD];
                $section = self::SECTION_SETTINGS;
                $tab = self::TAB_SETTINGS_GENERAL;

                $cmd = $this->ctrl->getCmd(self::DEFAULT_CMD);
                $cmd = strtolower($cmd);

                switch ($cmd) {
                    case self::CMD_SETTINGS_HELP_VIEW:
                        $method = self::ALLOWED_COMMANDS[self::CMD_SETTINGS_HELP_VIEW];
                        $tab = self::TAB_SETTINGS_HELP;
                        break;
                    case self::CMD_SETTINGS_REST_VIEW:
                    case self::CMD_SETTINGS_REST_SAVE:
                        $method = self::ALLOWED_COMMANDS[$cmd];
                        $tab = self::TAB_SETTINGS_REST;
                        break;
                    case self::ACTION_SAVE:
                    case self::CMD_SETTINGS_GENERAL_SAVE:
                        $method = self::ALLOWED_COMMANDS[self::CMD_SETTINGS_GENERAL_SAVE];
                        $tab = self::TAB_SETTINGS_GENERAL;
                        break;
                    case '':
                    case self::ACTION_VIEW:
                    case self::CMD_SETTINGS_GENERAL_VIEW:
                    default:
                        // skip and load defaults
                        break;
                }

                $this->tabs_gui->activateTab($section);
                $this->tabs_gui->activateSubTab($tab);

                $this->$method();

                break;
        }
    }

    public function showGeneralSettings(): void
    {
        $this->tpl->setContent(
            $this->ui_renderer->render(
                $this->createSettingsForm(
                    self::TAB_SETTINGS_GENERAL,
                )
            )
        );
    }

    public function updateGeneralSettings(): void
    {
        $this->saveSettingsForm(self::TAB_SETTINGS_GENERAL);
    }

    public function showRestSettings(): void
    {
        $this->tpl->setContent(
            $this->ui_renderer->render(
                $this->createSettingsForm(
                    self::TAB_SETTINGS_REST,
                )
            )
        );
    }

    public function updateRestSettings(): void
    {
        $this->saveSettingsForm(self::TAB_SETTINGS_REST);
    }

    public function showSettingsHelp(): void
    {
        $tpl = new \ilTemplate(
            file: "tpl.settings_help.html",
            flag1: true, // enable dynamic variables
            flag2: false, // disable HTML escaping
            in_module: "components/ILIAS/ApiGateway",
        );

        $this->tpl->setContent($tpl->get());
    }

    private function saveSettingsForm(string $tab): void
    {
        $a_cmd = match ($tab) {
            self::TAB_SETTINGS_GENERAL => self::CMD_SETTINGS_GENERAL_VIEW,
            self::TAB_SETTINGS_REST => self::CMD_SETTINGS_REST_VIEW,
            default => throw new Exception('Unknown tab'),
        };
        $form = $this->createSettingsForm($tab);

        $this->saveForm($form, $a_cmd);
    }

    private function saveForm(StandardForm $form, string $a_cmd): void
    {
        try {
            if (!$this->has_write_access) {
                throw new LogicException('Permission denied');
            }
            $form = $form->withRequest($this->http->request());
            $data = $form->getData();

            if (null === $data) {
                throw new InvalidArgumentException(
                    $this->lng->txt("EXCEPTION_NO_DATA_PROVIDED")
                );
            }

            $this->settings_service->setData($data);
            $this->settings_service->save();

            $this->tpl->setOnScreenMessage('success', $this->lng->txt("msg_obj_modified"), true);
            $this->ctrl->redirect($this, $a_cmd);
        } catch (\Throwable $e) {
            $this->tpl->setOnScreenMessage('failure', $e->getMessage(), true);
            $this->tpl->setContent($this->ui_renderer->render($form));
        }
    }

    private function createSettingsForm(string $tab): StandardForm
    {
        $cmd = match ($tab) {
            self::TAB_SETTINGS_GENERAL => self::CMD_SETTINGS_GENERAL_SAVE,
            self::TAB_SETTINGS_REST => self::CMD_SETTINGS_REST_SAVE,
            default => throw new InvalidArgumentException('Unknown tab: ' . $tab),
        };

        $form_action = $this->ctrl->getLinkTarget($this, $cmd);

        $class_name = GeneralSettings::class;

        if (array_key_exists($tab, self::TAB_FORMS)) {
            $class_name = self::TAB_FORMS[$tab];
        }

        /**
         * @var SettingsForm
         */
        $form = new $class_name(
            $this->settings_service,
            $this->ctrl,
            $this->lng,
            $this->ui_factory,
        );

        return $form->get($form_action);
    }

    public function getAdminTabs(): void
    {
        $this->getTabs();
    }

    protected function getTabs(): void
    {
        if ($this->has_read_access) {
            $this->tabs_gui->addTab(
                self::SECTION_SETTINGS,
                $this->lng->txt(self::SECTION_SETTINGS),
                $this->ctrl->getLinkTarget($this, self::CMD_SETTINGS_GENERAL_VIEW),
            );
            $this->tabs_gui->addTab(
                self::SECTION_PERMISSIONS,
                $this->lng->txt('perm_settings'),
                $this->ctrl->getLinkTargetByClass([self::class, ilPermissionGUI::class], 'perm'),
            );
        }

        $next_class = $this->ctrl->getNextClass($this);

        if (strtolower(ilPermissionGUI::class) == $next_class) {
            // skip
        }

        /**
         * Sections are defined at the end of the command.
         *
         * Example: 'view_rest_settings'
         *          - 'view' is the action
         *          - 'rest' is the tab
         *          - 'settings' is the section
         */
        $cmd = $this->ctrl->getCmd(self::DEFAULT_CMD);

        if (
            $cmd === '' ||
            strtolower($cmd) === 'view' ||
            str_ends_with(strtolower($cmd), self::KEYS_SEPARATOR . self::SECTION_SETTINGS)
        ) {
            if ($this->has_read_access) {
                $this->tabs_gui->addSubTab(
                    self::TAB_SETTINGS_GENERAL,
                    $this->lng->txt(self::TAB_SETTINGS_GENERAL),
                    $this->ctrl->getLinkTarget($this, self::CMD_SETTINGS_GENERAL_VIEW),
                );
                $this->tabs_gui->addSubTab(
                    self::TAB_SETTINGS_REST,
                    'REST',
                    $this->ctrl->getLinkTarget($this, self::CMD_SETTINGS_REST_VIEW),
                );
                $this->tabs_gui->addSubTab(
                    'settings_soap_legacy',
                    'SOAP',
                    $this->ctrl->getLinkTargetByClass(['ilObjAuthSettingsGUI'], 'editSOAP'),
                );
                $this->tabs_gui->addSubTab(
                    self::TAB_SETTINGS_HELP,
                    $this->lng->txt('help'),
                    $this->ctrl->getLinkTarget($this, self::CMD_SETTINGS_HELP_VIEW),
                );
            }
        }
    }
}
