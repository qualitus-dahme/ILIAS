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

namespace ILIAS\ApiGateway\Configuration\Admin;

use ILIAS\Administration\Setting;
use ILIAS\ApiGateway\Configuration\Domain\Enum\EncryptionAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Enum\HashingAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\ApiGateway\Configuration\Infrastructure\RandomKeyGenerator;
use ILIAS\Refinery\Factory;
use ilSetting;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Override;
use RuntimeException;

use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function strlen;

/**
 * @codeCoverageIgnore To be tested when settings are loaded by DI not global
 */
final class ilApiGatewaySettings implements SettingsService
{
    private const string MODULE_NAME = 'apigateway';
    private const string AUTH_SECRET_KEY = SystemSetting::AUTH_SECRET_KEY->value;
    private const string AUTH_ALGO_ENCRYPTION = SystemSetting::AUTH_ALGO_ENCRYPTION->value;
    private const string AUTH_ALGO_HASH = SystemSetting::AUTH_ALGO_HASH->value;
    private const string AUTH_TOKEN_EXPIRY_ACCESS = SystemSetting::AUTH_TOKEN_EXPIRY_ACCESS->value;
    private const string AUTH_TOKEN_EXPIRY_REFRESH = SystemSetting::AUTH_TOKEN_EXPIRY_REFRESH->value;
    private const string REST_WS_ENABLED = SystemSetting::REST_WS_ENABLED->value;
    private const string REST_DOCS_ENABLED = SystemSetting::REST_DOCS_ENABLED->value;
    protected static ?ilApiGatewaySettings $instance = null;
    private readonly Setting $settings;
    private readonly Factory $refinery;

    /** @var array<string, mixed> */
    private array $settings_data = [
        /**
         * defaults for system settings, load when specific setting is missing or empty
         */
        self::AUTH_SECRET_KEY => '', // generate randomly on installation
        self::AUTH_ALGO_ENCRYPTION => EncryptionAlgo::HS256->value,
        self::AUTH_ALGO_HASH => HashingAlgo::SHA256->value,
        self::AUTH_TOKEN_EXPIRY_ACCESS => "86400",
        self::AUTH_TOKEN_EXPIRY_REFRESH => "604800",
        self::REST_WS_ENABLED => '0',
        self::REST_DOCS_ENABLED => '0',
    ];

    private function __construct()
    {
        global $DIC;

        $this->settings = new ilSetting(self::MODULE_NAME, true);
        $this->refinery = $DIC->refinery();

        $this->read();
    }

    public static function getInstance(): ilApiGatewaySettings
    {
        if (!self::$instance instanceof ilApiGatewaySettings) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    #[Override]
    public function getData(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->settings_data;
        }

        if (!isset($this->settings_data[$key])) {
            return null;
        }

        return $this->settings_data[$key];
    }

    #[Override]
    public function setData(array $data): void
    {
        foreach ($data as $key => $value) {
            if (!is_string($value) && is_array($value)) {
                // cover form sections case where it is multidimensional arrays
                $this->setData($value);
                continue;
            }
            if (!isset($this->settings_data[$key])) {
                continue;
            }

            $this->settings_data[$key] = $value;
        }
    }

    #[Override]
    public function save(): void
    {
        $transformed = [];

        foreach ($this->settings_data as $key => $value) {
            $a_value = $value;

            if (is_bool($value)) {
                $a_value = $value ? '1' : '0';
            }

            if (!is_string($a_value) && !is_numeric($a_value)) {
                try {
                    $a_value = json_encode(
                        $a_value,
                        JSON_THROW_ON_ERROR
                            | JSON_HEX_TAG
                            | JSON_HEX_AMP
                            | JSON_HEX_APOS
                            | JSON_HEX_QUOT
                    );
                } catch (JsonException $e) {
                    throw new RuntimeException(
                        sprintf(
                            'Failed to encode value for setting %s: %s',
                            $key,
                            $e->getMessage(),
                        ),
                        0,
                        $e,
                    );
                }
            }

            /** @var string */
            $a_value = $this->refinery->kindlyTo()->string()->transform($a_value);

            switch ($key) {
                case self::AUTH_SECRET_KEY:
                    if ($a_value === '') {
                        $a_value = RandomKeyGenerator::generate();
                    }

                    break;
                case self::AUTH_ALGO_ENCRYPTION:
                    $a_value = EncryptionAlgo::tryFrom($a_value)?->value;

                    if (null === $a_value) {
                        throw new RuntimeException("Invalid value for encryption algorithm.");
                    }

                    break;
                case self::AUTH_ALGO_HASH:
                    $a_value = HashingAlgo::tryFrom($a_value)?->value;

                    if (null === $a_value) {
                        throw new RuntimeException("Invalid value for hashing algorithm.");
                    }

                    break;
                case self::AUTH_TOKEN_EXPIRY_REFRESH:
                case self::AUTH_TOKEN_EXPIRY_ACCESS:
                    if (!is_numeric($a_value)) {
                        throw new InvalidArgumentException("Invalid value for expiry token. Must be numeric.");
                    }

                    if ((int) $a_value < 1) {
                        throw new InvalidArgumentException("Invalid value for expiry token. Must be greater than 0.");
                    }

                    break;
                case self::REST_WS_ENABLED:
                    if ($a_value == '0') {
                        // skip check if service is or being disabled
                        break;
                    }
                    /** @var string */
                    $secret_key = $this->settings_data[self::AUTH_SECRET_KEY];
                    $secret_key_length = strlen($secret_key);
                    /** @var string */
                    $encryption_algo = $this->settings_data[self::AUTH_ALGO_ENCRYPTION];
                    $min_length = EncryptionAlgo::from($encryption_algo)->getKeyMinimumLength();

                    if ($secret_key_length < $min_length) {
                        throw new LogicException(
                            "Invalid secret key length. Minimum required is {$min_length} characters, but key is {$secret_key_length} characters long."
                        );
                    }

                    break;
            }

            $transformed[$key] = $this->settings_data[$key] = $a_value;
        }

        // Persist only after all validations pass
        foreach ($transformed as $key => $a_value) {
            $this->settings->set(
                $key,
                $a_value,
            );
        }
    }

    private function read(): void
    {
        foreach ($this->settings_data as $key => $default_value) {
            $value = $this->settings->get($key) ?? $default_value;

            $this->settings_data[$key] = $value;
        }
    }
}
