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

namespace ILIAS\ApiGateway\Setup\Objectives;

use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\Setup;
use ILIAS\Setup\Metrics;
use Override;

use function sprintf;

class ApiGatewayMetricsCollectedObjective extends Metrics\CollectedObjective
{
    #[Override]
    protected function getTentativePreconditions(Setup\Environment $environment): array
    {
        return [
            new \ilSettingsFactoryExistsObjective()
        ];
    }

    #[Override]
    protected function collectFrom(Setup\Environment $environment, Metrics\Storage $storage): void
    {
        $factory = $environment->getResource(Setup\Environment::RESOURCE_SETTINGS_FACTORY);
        if (!$factory instanceof \ilSettingsFactory) {
            return;
        }
        $settings = $factory->settingsFor('apigateway');

        $getConfigValue = fn(string $key, string $default): string => $settings->get($key, $default) ?? $default;
        $getProtocolConfigValue = fn(string $protocol, string $key, string $default): string => $getConfigValue("{$protocol}_{$key}", $default);

        foreach (ServiceProtocol::cases() as $protocol) {

            $protocol_key = $protocol->value;
            $protocol_name = strtoupper($protocol->value);

            $protocol_enabled = (bool) $getProtocolConfigValue($protocol_key, 'ws_enabled', '0');

            $storage->storeConfigBool(
                "{$protocol_key}_enabled",
                $protocol_enabled,
                sprintf(
                    '%s webservice enabled. %s',
                    $protocol_name,
                    $protocol_enabled ? 'Yes' : 'No',
                )
            );

            if ($protocol_enabled) {

                $docs_enabled = (bool) $getProtocolConfigValue($protocol_key, 'docs_enabled', '0');

                $storage->storeConfigBool(
                    "{$protocol_key}_docs_enabled",
                    $docs_enabled,
                    sprintf(
                        '%s documentation enabled. %s',
                        $protocol_name,
                        $docs_enabled ? 'Yes' : 'No',
                    )
                );
            }
        }
    }

    #[Override]
    public function getLabel(): string
    {
        return 'Component ApiGateway metrics';
    }
}
