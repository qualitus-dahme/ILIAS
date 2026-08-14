<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Domain\Model;

use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\ApiGateway\Configuration\Domain\Model\Setting;
use ILIAS\ApiGateway\Configuration\Domain\Model\SystemSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemSettings::class)]
final class SystemSettingsTest extends TestCase
{
    public function testFindReturnsSettingWhenKeyExists(): void
    {
        $expected = 'my_secret_key';
        $systemSettings = SystemSettings::create([
            SystemSetting::AUTH_SECRET_KEY->value => $expected,
            SystemSetting::AUTH_ALGO_ENCRYPTION->value => 'HS256'
        ]);

        $actual = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);

        self::assertInstanceOf(Setting::class, $actual);
        self::assertSame(SystemSetting::AUTH_SECRET_KEY->value, $actual->getKey());
        self::assertSame($expected, $actual->asString());
    }

    public function testFindReturnsNullWhenKeyDoesNotExist(): void
    {
        $systemSettings = SystemSettings::create([
            SystemSetting::AUTH_ALGO_ENCRYPTION->value => 'HS256'
        ]);

        $actual = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);

        self::assertNull($actual);
    }

    public function testFindReturnsNullWhenSystemSettingsIsEmpty(): void
    {
        $systemSettings = SystemSettings::create([]);

        $actual = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);

        self::assertNull($actual);
    }

    /**
     *
     * SystemSettings::create() test cases
     *
     */

    public function testCreationWithValidData(): void
    {
        $settingsData = [
            SystemSetting::AUTH_SECRET_KEY->value => $secretKey = 'test_secret',
            SystemSetting::AUTH_ALGO_ENCRYPTION->value => $algo = 'HS256',
        ];

        $systemSettings = SystemSettings::create($settingsData);

        $secretKeySetting = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);
        $algoSetting = $systemSettings->find(SystemSetting::AUTH_ALGO_ENCRYPTION);

        self::assertNotNull($secretKeySetting);
        self::assertSame($secretKey, $secretKeySetting->asString());
        self::assertNotNull($algoSetting);
        self::assertSame($algo, $algoSetting->asString());
    }

    public function testCreationWithEmptyArray(): void
    {
        $systemSettings = SystemSettings::create([]);

        $secretKeySetting = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);
        $algoSetting = $systemSettings->find(SystemSetting::AUTH_ALGO_ENCRYPTION);

        self::assertNull($secretKeySetting);
        self::assertNull($algoSetting);
    }

    public function testCreationIgnoresInvalidKeys(): void
    {
        $settingsData = [
            'invalid_key' => 'some_value',
            SystemSetting::AUTH_SECRET_KEY->value => 'test_secret',
        ];

        $systemSettings = SystemSettings::create($settingsData);
        $actual = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);

        self::assertNotNull($actual);
    }

    public function testCreationIgnoresNullValues(): void
    {
        $settingsData = [
            SystemSetting::AUTH_SECRET_KEY->value => null,
            SystemSetting::AUTH_ALGO_ENCRYPTION->value => $algo = 'HS256',
        ];

        $systemSettings = SystemSettings::create($settingsData);

        $secretKeySetting = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);
        $algoSetting = $systemSettings->find(SystemSetting::AUTH_ALGO_ENCRYPTION);

        self::assertNull($secretKeySetting);
        self::assertNotNull($algoSetting);
        self::assertSame($algo, $algoSetting->asString());
    }

    #[DataProvider('valuesForCastingDataProvider')]
    public function testCreationCastsValuesToString(mixed $value, string $expectedString): void
    {
        $settingsData = [
            SystemSetting::AUTH_SECRET_KEY->value => $value,
        ];

        $systemSettings = SystemSettings::create($settingsData);
        $actual = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);

        self::assertNotNull($actual);
        self::assertSame($expectedString, $actual->asString());
    }

    public function testCreationWithMixedData(): void
    {
        $settingsData = [
            SystemSetting::AUTH_SECRET_KEY->value => $secretKey = 'test_secret',
            'invalid_key' => 'some_value',
            SystemSetting::AUTH_ALGO_ENCRYPTION->value => null,
        ];

        $systemSettings = SystemSettings::create($settingsData);
        $secretKeySetting = $systemSettings->find(SystemSetting::AUTH_SECRET_KEY);
        $algoSetting = $systemSettings->find(SystemSetting::AUTH_ALGO_ENCRYPTION);

        self::assertNotNull($secretKeySetting);
        self::assertSame($secretKey, $secretKeySetting->asString());
        self::assertNull($algoSetting);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function valuesForCastingDataProvider(): array
    {
        return [
            'Integer' => [123, '123'],
            'Float' => [1.23, '1.23'],
            'Boolean true' => [true, '1'],
            'Boolean false' => [false, ''],
        ];
    }
}
