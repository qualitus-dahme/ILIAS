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

use ILIAS\DI\Container;
use ILIAS\Notifications\Repository\ilNotificationOSDRepository;
use ILIAS\UI\Component\Input\Container\Form\Form;

/**
 * @author            Ingmar Szmais <iszmais@databay.de>
 *
 * @ilCtrl_IsCalledBy ilObjNotificationAdminGUI: ilAdministrationGUI
 * @ilCtrl_Calls      ilObjNotificationAdminGUI: ilPermissionGUI
 */
class ilObjNotificationAdminGUI extends ilObjectGUI
{
    protected Container $dic;

    public function __construct($a_data, int $a_id = 0, bool $a_call_by_reference = true, bool $a_prepare_output = true)
    {
        global $DIC;

        $this->dic = $DIC;

        $this->type = 'nota';
        parent::__construct($a_data, $a_id, $a_call_by_reference, false);
        $this->lng->loadLanguageModule('notifications_adm');
    }

    public function executeCommand(): void
    {
        if (!$this->rbac_system->checkAccess('visible,read', $this->object->getRefId())) {
            $this->error->raiseError($this->lng->txt('no_permission'), $this->error->WARNING);
        }

        $this->prepareOutput();
        $this->tabs_gui->activateTab('settings');

        switch (strtolower($this->ctrl->getNextClass())) {
            case strtolower(ilPermissionGUI::class):
                $perm_gui = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($perm_gui);
                break;
            default:
                match ($this->ctrl->getCmd()) {
                    'saveOSDSettings' => $this->saveOSDSettings(),
                    // no break
                    default => $this->showOSDSettings(),
                };
        }
    }

    public function getAdminTabs(): void
    {
        if ($this->checkPermissionBool('visible,read')) {
            $this->tabs_gui->addTab(
                'settings',
                $this->lng->txt('settings'),
                $this->ctrl->getLinkTarget($this, 'editSettings')
            );
        }

        if ($this->checkPermissionBool('edit_permission')) {
            $this->tabs_gui->addTab(
                'perm_settings',
                $this->lng->txt('perm_settings'),
                $this->ctrl->getLinkTargetByClass([$this::class, ilPermissionGUI::class], 'perm')
            );
        }
    }

    /**
     * @throws ilCtrlException
     */
    public function showOSDSettings(?Form $form = null): void
    {
        if ($form === null) {
            $settings = new ilSetting('notifications');
            $values = [];
            if ($settings->get('enable_osd') === '0' || $settings->get('enable_osd') === null) {
                $values['enable_osd'] = null;
            } else {
                $values['enable_osd'] = [
                    'osd_interval' => (int) $settings->get('osd_interval'),
                    'osd_vanish' => (int) $settings->get('osd_vanish'),
                    'osd_delay' => (int) $settings->get('osd_delay'),
                    'osd_play_sound' => (bool) $settings->get('osd_play_sound'),
                ];
            }
            $form = $this->getForm($values);
        }

        $this->tpl->setContent($this->dic->ui()->renderer()->render($form));
    }

    /**
     * @throws ilCtrlException
     */
    public function saveOSDSettings(): void
    {
        if (!$this->checkPermissionBool('write')) {
            $this->error->raiseError($this->lng->txt('permission_denied'), $this->error->MESSAGE);
        }

        $settings = new ilSetting('notifications');

        $form = $this->getForm()->withRequest($this->dic->http()->request());
        $data = $form->getData();
        if (isset($data['osd']) && is_array($data['osd'])) {
            if (!isset($data['osd']['enable_osd'])) {
                global $DIC;
                $DIC->notifications()->system()->clear('osd');
                $settings->set('enable_osd', '0');
                $settings->delete('osd_interval');
                $settings->delete('osd_vanish');
                $settings->delete('osd_delay');
                $settings->delete('osd_play_sound');
            } else {
                $settings->set('enable_osd', '1');
                $settings->set('osd_interval', ((string) $data['osd']['enable_osd']['osd_interval']));
                $settings->set('osd_vanish', ((string) $data['osd']['enable_osd']['osd_vanish']));
                $settings->set('osd_delay', ((string) $data['osd']['enable_osd']['osd_delay']));
                $settings->set('osd_play_sound', ($data['osd']['enable_osd']['osd_play_sound']) ? '1' : '0');

            }
        }
        $this->showOSDSettings($form);
    }

    /**
     * @param array<string, mixed>|null $values
     * @throws ilCtrlException
     */
    protected function getForm(?array $values = null): Form
    {
        $enable_osd = $this->dic->ui()->factory()->input()->field()->optionalGroup(
            [
                'osd_interval' => $this->dic->ui()->factory()->input()->field()->numeric(
                    $this->lng->txt('osd_interval'),
                    $this->lng->txt('osd_interval_desc')
                )
                    ->withRequired(true)
                    ->withValue(60000)
                    ->withAdditionalTransformation($this->dic->refinery()->custom()->constraint(
                        static function ($value) {
                            return $value >= 3000;
                        },
                        $this->lng->txt('osd_error_refresh_interval_too_small')
                    )),
                'osd_vanish' => $this->dic->ui()->factory()->input()->field()->numeric(
                    $this->lng->txt('osd_vanish'),
                    $this->lng->txt('osd_vanish_desc')
                )
                    ->withRequired(true)
                    ->withValue(5000)
                    ->withAdditionalTransformation($this->dic->refinery()->custom()->constraint(
                        static function ($value) {
                            return $value >= 1000;
                        },
                        $this->lng->txt('osd_error_presentation_time_too_small')
                    )),
                'osd_delay' => $this->dic->ui()->factory()->input()->field()->numeric(
                    $this->lng->txt('osd_delay'),
                    $this->lng->txt('osd_delay_desc')
                )
                    ->withRequired(true)
                    ->withValue(500),
                'osd_play_sound' => $this->dic->ui()->factory()->input()->field()->checkbox(
                    $this->lng->txt('osd_play_sound'),
                    $this->lng->txt('osd_play_sound_desc')
                )
            ],
            $this->lng->txt('enable_osd')
        )->withByline(
            $this->lng->txt('enable_osd_desc')
        )->withAdditionalTransformation(
            $this->dic->refinery()->custom()->constraint(
                static function ($value) {
                    return $value === null || ($value['osd_interval'] > $value['osd_delay'] + $value['osd_vanish']);
                },
                $this->lng->txt('osd_error_refresh_interval_smaller_than_delay_and_vanish_combined')
            )
        );

        if ($values !== null) {
            $enable_osd = $enable_osd->withValue($values['enable_osd'] ?? null);
        }

        return $this->dic->ui()->factory()->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'saveOSDSettings'),
            [
                'osd' => $this->dic->ui()->factory()->input()->field()->section(
                    ['enable_osd' => $enable_osd],
                    $this->lng->txt('osd_settings')
                )
            ]
        );
    }
}
