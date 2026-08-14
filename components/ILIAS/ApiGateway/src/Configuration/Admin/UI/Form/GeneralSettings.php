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

use ILIAS\ApiGateway\Configuration\Domain\Enum\EncryptionAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Enum\HashingAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\ApiGateway\Configuration\Admin\UI\Form\SettingsForm;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

final readonly class GeneralSettings extends SettingsForm
{
    #[\Override]
    public function get(string $form_action): StandardForm
    {
        $auth_secret_key = SystemSetting::AUTH_SECRET_KEY->value;
        $auth_algo_encryption = SystemSetting::AUTH_ALGO_ENCRYPTION->value;
        $auth_algo_hash = SystemSetting::AUTH_ALGO_HASH->value;
        $auth_token_expiry_access = SystemSetting::AUTH_TOKEN_EXPIRY_ACCESS->value;
        $auth_token_expiry_refresh = SystemSetting::AUTH_TOKEN_EXPIRY_REFRESH->value;

        $encryptionOptions = $hashingOptions = [];

        foreach (EncryptionAlgo::cases() as $algo) {
            $encryptionOptions[$algo->value] = $algo->value;
        }

        foreach (HashingAlgo::cases() as $algo) {
            $hashingOptions[$algo->value] = strtoupper($algo->value);
        }

        $auth_secret_key_value = $this->settings_service->getData($auth_secret_key);
        $auth_algo_encryption_value = $this->settings_service->getData($auth_algo_encryption);
        $auth_algo_hash_value = $this->settings_service->getData($auth_algo_hash);
        $auth_token_expiry_access_value = $this->settings_service->getData($auth_token_expiry_access);
        $auth_token_expiry_refresh_value = $this->settings_service->getData($auth_token_expiry_refresh);

        $inputs = [
            $auth_secret_key => $this->ui_factory->input()->field()->text(
                $this->lng->txt("{$auth_secret_key}_label"),
                $this->lng->txt("{$auth_secret_key}_info")
            )->withValue($auth_secret_key_value),

            $auth_algo_encryption => $this->ui_factory->input()->field()->select(
                $this->lng->txt("{$auth_algo_encryption}_label"),
                $encryptionOptions,
                $this->lng->txt("{$auth_algo_encryption}_info")
            )->withValue($auth_algo_encryption_value),

            $auth_algo_hash => $this->ui_factory->input()->field()->select(
                $this->lng->txt("{$auth_algo_hash}_label"),
                $hashingOptions,
                $this->lng->txt("{$auth_algo_hash}_info")
            )->withValue($auth_algo_hash_value),

            $auth_token_expiry_access => $this->ui_factory->input()->field()->numeric(
                $this->lng->txt("{$auth_token_expiry_access}_label"),
                $this->lng->txt("{$auth_token_expiry_access}_info")
            )->withValue($auth_token_expiry_access_value),

            $auth_token_expiry_refresh => $this->ui_factory->input()->field()->numeric(
                $this->lng->txt("{$auth_token_expiry_refresh}_label"),
                $this->lng->txt("{$auth_token_expiry_refresh}_info")
            )->withValue($auth_token_expiry_refresh_value),

        ];

        $ff = $this->ui_factory->input()->field();

        $settings = $ff->section(
            $inputs,
            $this->lng->txt('general_settings'),
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
