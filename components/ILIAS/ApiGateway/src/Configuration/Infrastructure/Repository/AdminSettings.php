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

namespace ILIAS\ApiGateway\Configuration\Infrastructure\Repository;

use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\ApiGateway\Configuration\Domain\Model\Setting;
use ILIAS\ApiGateway\Configuration\Domain\SystemSettingRepository;
use ILIAS\ApiGateway\GlobalDICAccessTrait;
use ilDBInterface;
use ilSetting;

/**
 * @codeCoverageIgnore To be tested when settings are loaded by DI not global
 */
final class AdminSettings implements SystemSettingRepository
{
    use GlobalDICAccessTrait;

    private const string MODULE_NAME = 'apigateway';
    private ?ilSetting $settings = null;

    #[\Override]
    public function get(SystemSetting $settingKey): Setting
    {
        $key = $settingKey->value;
        $value = $this->settings()?->get($key);

        return Setting::create($key, $value);
    }

    private function settings(): ?ilSetting
    {
        if (!$this->settings instanceof ilSetting) {
            $database = $this->database();

            if (!$database instanceof ilDBInterface) {
                return null;
            }

            $this->settings = new ilSetting(self::MODULE_NAME, true);
        }

        return $this->settings;
    }
}
