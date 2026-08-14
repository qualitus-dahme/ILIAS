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

namespace ILIAS\ApiGateway\Configuration\Domain\Model;

use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;

class SystemSettings
{
    /**
     * @param array<string, mixed> $settingsData
     */
    public static function create(array $settingsData): self
    {
        $settings = [];

        foreach ($settingsData as $keyString => $value) {

            $key = SystemSetting::tryFrom($keyString);

            if ($key === null) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $settings[] = Setting::create($key->value, $value);
        }

        return new self(...$settings);
    }

    /**
     * @var Setting[] $settings
     */
    private array $settings = [];

    public function __construct(
        Setting ...$settings,
    ) {
        $this->settings = $settings;
    }

    public function find(SystemSetting $key): ?Setting
    {
        foreach ($this->settings as $setting) {
            if ($setting->getKey() === $key->value) {
                return $setting;
            }
        }

        return null;
    }
}
