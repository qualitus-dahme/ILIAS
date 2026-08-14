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

namespace ILIAS\ApiGateway\Configuration\Infrastructure;

use ILIAS\ApiGateway\Configuration\Domain\Configuration;
use ILIAS\ApiGateway\Configuration\Domain\Enum\EncryptionAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Enum\HashingAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\ApiGateway\Configuration\Domain\SystemSettingRepository;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use Override;

use function defined;

class ConfigurationService implements Configuration
{
    public const string DEFAULT_BASE_URL = 'http://localhost:8080';
    public const string DEFAULT_CLIENT_ID = 'ILIAS';
    public const EncryptionAlgo DEFAULT_ALGO_ENCRYPT = EncryptionAlgo::HS256;
    public const HashingAlgo DEFAULT_ALGO_HASH = HashingAlgo::SHA256;
    public const int DEFAULT_ACCESS_TOKEN_EXPIRE_IN = 86400; // 1 day
    public const int DEFAULT_REFRESH_TOKEN_EXPIRE_IN = 604800; // 7 days
    public const bool DEFAULT_IS_WEBSERVICE_ENABLED = false;
    public const bool DEFAULT_IS_WEBSERVICE_DOCS_ENABLED = false;
    public const bool DEFAULT_IS_DEBUG_ENABLED = false;
    public const bool DEFAULT_IS_LOGGING_ENABLED = true;
    public const bool DEFAULT_IS_LOGGING_DETAILS_ENABLED = false;

    public function __construct(
        private readonly SystemSettingRepository $adminSettings,
    ) {
    }

    #[Override]
    public function getBaseUrl(): string
    {
        $value = defined('ILIAS_HTTP_PATH')
            ? trim(rtrim((string) ILIAS_HTTP_PATH, '/'))
            : '';

        return $value === '' || $value === '0' ? self::DEFAULT_BASE_URL : $value;
    }

    #[Override]
    public function getClientId(): string
    {
        return defined('CLIENT_ID')
            ? CLIENT_ID
            : self::DEFAULT_CLIENT_ID;
    }

    #[Override]
    public function getSecretKey(): string
    {
        return $this->adminSettings->get(SystemSetting::AUTH_SECRET_KEY)->asString();
    }

    #[Override]
    public function getEncryption(): string
    {
        $settingValue = $this->adminSettings->get(SystemSetting::AUTH_ALGO_ENCRYPTION)->asString();
        $value = EncryptionAlgo::tryFrom($settingValue) ?? self::DEFAULT_ALGO_ENCRYPT;

        return $value->value;
    }

    #[Override]
    public function getHashing(): string
    {
        $settingValue = $this->adminSettings->get(SystemSetting::AUTH_ALGO_HASH)->asString();
        $value = HashingAlgo::tryFrom($settingValue) ?? self::DEFAULT_ALGO_HASH;

        return $value->value;
    }

    #[Override]
    public function getAccessTokenExpiry(): int
    {
        $value = $this->adminSettings->get(SystemSetting::AUTH_TOKEN_EXPIRY_ACCESS)->asInt();

        return $value > 0 ? $value : self::DEFAULT_ACCESS_TOKEN_EXPIRE_IN;
    }

    #[Override]
    public function getRefreshTokenExpiry(): int
    {
        $value = $this->adminSettings->get(SystemSetting::AUTH_TOKEN_EXPIRY_REFRESH)->asInt();

        return $value > 0 ? $value : self::DEFAULT_REFRESH_TOKEN_EXPIRE_IN;
    }

    #[Override]
    public function isEnabled(ServiceProtocol $protocol): bool
    {
        return match ($protocol) {
            ServiceProtocol::REST => $this->adminSettings->get(SystemSetting::REST_WS_ENABLED)->asBool(),
            default => self::DEFAULT_IS_WEBSERVICE_ENABLED,
        };
    }

    #[Override]
    public function isDocsEnabled(ServiceProtocol $protocol): bool
    {
        return match ($protocol) {
            ServiceProtocol::REST => $this->adminSettings->get(SystemSetting::REST_DOCS_ENABLED)->asBool(),
            default => self::DEFAULT_IS_WEBSERVICE_DOCS_ENABLED,
        };
    }

    #[Override]
    public function isDebugEnabled(): bool
    {
        return defined('DEVMODE')
            ? (bool) DEVMODE
            : self::DEFAULT_IS_DEBUG_ENABLED;
    }

    #[Override]
    public function isLoggingEnabled(): bool
    {
        return defined('DEVMODE')
            ? (bool) DEVMODE
            : self::DEFAULT_IS_LOGGING_ENABLED;
    }

    #[Override]
    public function isLoggingDetailsEnabled(): bool
    {
        return defined('DEVMODE')
            ? (bool) DEVMODE
            : self::DEFAULT_IS_LOGGING_DETAILS_ENABLED;
    }
}
