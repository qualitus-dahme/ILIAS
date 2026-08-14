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

namespace ILIAS\ApiGateway\Configuration\Admin\UI\Form;

use ILIAS\ApiGateway\Configuration\Admin\UI\Form\SettingsForm;
use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

final readonly class RestSettings extends SettingsForm
{
    #[\Override]
    public function get(string $form_action): StandardForm
    {
        $rest_ws_enabled = SystemSetting::REST_WS_ENABLED->value;
        $rest_docs_enabled = SystemSetting::REST_DOCS_ENABLED->value;

        $rest_ws_enabled_value = $this->settings_service->getData($rest_ws_enabled);
        $rest_docs_enabled_value = $this->settings_service->getData($rest_docs_enabled);

        $inputs = [
            $rest_ws_enabled => $this->ui_factory->input()->field()->checkbox(
                $this->lng->txt("{$rest_ws_enabled}_label"),
                $this->lng->txt("{$rest_ws_enabled}_info")
            )->withValue((bool) $rest_ws_enabled_value),

            $rest_docs_enabled => $this->ui_factory->input()->field()->checkbox(
                $this->lng->txt("{$rest_docs_enabled}_label"),
                $this->lng->txt("{$rest_docs_enabled}_info")
            )->withValue((bool) $rest_docs_enabled_value),
        ];

        $ff = $this->ui_factory->input()->field();

        $settings = $ff->section(
            $inputs,
            $this->lng->txt('rest_settings'),
        );

        return $this->ui_factory->input()
            ->container()
            ->form()
            ->standard(
                $form_action,
                [$settings],
            );
    }
}
