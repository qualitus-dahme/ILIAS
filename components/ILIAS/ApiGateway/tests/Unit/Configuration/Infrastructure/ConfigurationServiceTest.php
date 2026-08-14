<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Infrastructure;

use ILIAS\ApiGateway\Configuration\Domain\Enum\SystemSetting;
use ILIAS\ApiGateway\Configuration\Domain\Model\Setting;
use ILIAS\ApiGateway\Configuration\Domain\SystemSettingRepository;
use ILIAS\ApiGateway\Configuration\Infrastructure\ConfigurationService;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationService::class)]
class ConfigurationServiceTest extends TestCase
{
    private MockObject&SystemSettingRepository $systemSettingRepositoryMock;
    private ConfigurationService $service;

    #[\Override]
    protected function setUp(): void
    {
        $this->systemSettingRepositoryMock = $this->createMock(SystemSettingRepository::class);
        $this->service = new ConfigurationService($this->systemSettingRepositoryMock);
    }

    /**
     * Test is to always make sure configuration is present with safe input
     */
    #[Test]
    #[RunInSeparateProcess]
    public function testReturnsDefaultValues(): void
    {
        $settingMock = $this->createConfiguredMock(Setting::class, [
            'asString' => '',
            'asInt' => 0,
            'asBool' => false,
        ]);

        $this->systemSettingRepositoryMock->method('get')->willReturn($settingMock);

        $this->assertSame(ConfigurationService::DEFAULT_BASE_URL, $this->service->getBaseUrl());
        $this->assertSame(ConfigurationService::DEFAULT_CLIENT_ID, $this->service->getClientId());
        $this->assertSame('', $this->service->getSecretKey()); // don't generate secret key in order to prevent conflicts
        $this->assertSame(ConfigurationService::DEFAULT_ALGO_ENCRYPT->value, $this->service->getEncryption());
        $this->assertSame(ConfigurationService::DEFAULT_ALGO_HASH->value, $this->service->getHashing());
        $this->assertSame(ConfigurationService::DEFAULT_ACCESS_TOKEN_EXPIRE_IN, $this->service->getAccessTokenExpiry());
        $this->assertSame(ConfigurationService::DEFAULT_REFRESH_TOKEN_EXPIRE_IN, $this->service->getRefreshTokenExpiry());
        $this->assertSame(ConfigurationService::DEFAULT_IS_WEBSERVICE_ENABLED, $this->service->isEnabled(ServiceProtocol::SOAP));
        $this->assertSame(ConfigurationService::DEFAULT_IS_WEBSERVICE_DOCS_ENABLED, $this->service->isDocsEnabled(ServiceProtocol::SOAP));
        $this->assertSame(ConfigurationService::DEFAULT_IS_DEBUG_ENABLED, $this->service->isDebugEnabled());
        $this->assertSame(ConfigurationService::DEFAULT_IS_LOGGING_ENABLED, $this->service->isLoggingEnabled());
        $this->assertSame(ConfigurationService::DEFAULT_IS_LOGGING_DETAILS_ENABLED, $this->service->isLoggingDetailsEnabled());
    }

    #[Test]
    #[DataProvider('baseUrlDataProvider')]
    #[RunInSeparateProcess]
    public function testReturnsBaseUrlConstantValue(string $value, string $expected): void
    {
        define('ILIAS_HTTP_PATH', $value);

        $this->systemSettingRepositoryMock->expects($this->never())->method('get');

        $actual = $this->service->getBaseUrl();

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function baseUrlDataProvider(): array
    {
        return [
            'valid' => ['http://my-ilias.com/path', 'http://my-ilias.com/path'],
            'trim trailing slash' => ['http://my-ilias.com/path/', 'http://my-ilias.com/path'],
            'trim trailing slashes' => ['http://my-ilias.com/path////', 'http://my-ilias.com/path'],
            'trim spaces' => [' http://my-ilias.com/path ', 'http://my-ilias.com/path'],
            'just slash' => ['/', ConfigurationService::DEFAULT_BASE_URL],
        ];
    }

    #[Test]
    #[RunInSeparateProcess]
    public function testReturnsClientIdConstantValue(): void
    {
        $expected = 'MY_CLIENT';
        define('CLIENT_ID', $expected);

        $this->systemSettingRepositoryMock->expects($this->never())->method('get');

        $actual = $this->service->getClientId();

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testReturnsSecretKeySystemSettingsValue(): void
    {
        $expected = 'my-secret';
        $this->mockSystemSetting(SystemSetting::AUTH_SECRET_KEY, 'asString', $expected);

        $actual = $this->service->getSecretKey();

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testReturnsEncryptionAlgoSystemSettingsValue(): void
    {
        $expected = 'HS512';

        $this->mockSystemSetting(SystemSetting::AUTH_ALGO_ENCRYPTION, 'asString', $expected);

        $actual = $this->service->getEncryption();

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testReturnsDefaultEncryptionAlgoInCaseOfSystemSettingsInvalidValue(): void
    {
        $this->mockSystemSetting(SystemSetting::AUTH_ALGO_ENCRYPTION, 'asString', 'invalid-algo');

        $actual = $this->service->getEncryption();

        self::assertSame(ConfigurationService::DEFAULT_ALGO_ENCRYPT->value, $actual);
    }

    #[Test]
    public function testReturnsHashingAlgoSystemSettingsValue(): void
    {
        $expected = 'sha512';

        $this->mockSystemSetting(SystemSetting::AUTH_ALGO_HASH, 'asString', $expected);

        $actual = $this->service->getHashing();

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testReturnsDefaultHashingAlgoInCaseOfSystemSettingsInvalidValue(): void
    {
        $this->mockSystemSetting(SystemSetting::AUTH_ALGO_HASH, 'asString', 'invalid-algo');

        $actual = $this->service->getHashing();

        self::assertSame(ConfigurationService::DEFAULT_ALGO_HASH->value, $actual);
    }

    #[Test]
    public function testReturnsAccessTokenExpirySystemSettingsValue(): void
    {
        $expected = 1234;

        $this->mockSystemSetting(SystemSetting::AUTH_TOKEN_EXPIRY_ACCESS, 'asInt', $expected);

        $actual = $this->service->getAccessTokenExpiry();

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testReturnsDefaultAccessTokenExpiryInCaseOfSystemSettingsInvalidValue(): void
    {
        $value = -1234;

        $this->mockSystemSetting(SystemSetting::AUTH_TOKEN_EXPIRY_ACCESS, 'asInt', $value);

        $actual = $this->service->getAccessTokenExpiry();

        self::assertSame(ConfigurationService::DEFAULT_ACCESS_TOKEN_EXPIRE_IN, $actual);
    }

    #[Test]
    public function testReturnsRefreshTokenExpirySystemSettingsValue(): void
    {
        $expected = 1234;

        $this->mockSystemSetting(SystemSetting::AUTH_TOKEN_EXPIRY_REFRESH, 'asInt', $expected);

        $actual = $this->service->getRefreshTokenExpiry();

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testReturnsDefaultRefreshTokenExpiryInCaseOfSystemSettingsInvalidValue(): void
    {
        $value = -1234;

        $this->mockSystemSetting(SystemSetting::AUTH_TOKEN_EXPIRY_REFRESH, 'asInt', $value);

        $actual = $this->service->getRefreshTokenExpiry();

        self::assertSame(ConfigurationService::DEFAULT_REFRESH_TOKEN_EXPIRE_IN, $actual);
    }

    #[Test]
    #[DataProvider('serviceProtocolProvider')]
    public function testReturnsWebserviceIsEnabled(bool $expected): void
    {
        $this->mockSystemSetting(SystemSetting::REST_WS_ENABLED, 'asBool', $expected);

        $isEnabled = $this->service->isEnabled(ServiceProtocol::REST);

        self::assertSame($expected, $isEnabled);
    }

    #[Test]
    public function testReturnsWebserviceIsEnabledFalseInCaseOfUnsupportedProtocol(): void
    {
        $this->systemSettingRepositoryMock->expects($this->never())->method('get');

        $isEnabled = $this->service->isEnabled(ServiceProtocol::SOAP);

        self::assertFalse($isEnabled);
    }


    #[Test]
    #[DataProvider('serviceProtocolProvider')]
    public function testReturnsWebserviceDocsIsEnabled(bool $expected): void
    {
        $this->mockSystemSetting(SystemSetting::REST_DOCS_ENABLED, 'asBool', $expected);

        $isDocsEnabled = $this->service->isDocsEnabled(ServiceProtocol::REST);

        self::assertSame($expected, $isDocsEnabled);
    }

    #[Test]
    public function testReturnsWebserviceDocsIsEnabledFalseInCaseOfUnsupportedProtocol(): void
    {
        $this->systemSettingRepositoryMock->expects($this->never())->method('get');

        $isDocsEnabled = $this->service->isDocsEnabled(ServiceProtocol::SOAP);

        self::assertFalse($isDocsEnabled);
    }

    /**
     * @return array<string, array<array-key, bool>>
     */
    public static function serviceProtocolProvider(): array
    {
        return [
            'enabled' => [true],
            'disabled' => [false],
        ];
    }

    #[Test]
    #[DataProvider('trueDevModeDataProvider')]
    #[RunInSeparateProcess]
    public function testReactsOnDevModeConstantWhenTrue(int|bool|string $value): void
    {
        define('DEVMODE', $value);

        self::assertTrue($this->service->isDebugEnabled());
        self::assertTrue($this->service->isLoggingEnabled());
        self::assertTrue($this->service->isLoggingDetailsEnabled());
    }

    /**
     * @return array<string, array{int|bool|string}>
     */
    public static function trueDevModeDataProvider(): array
    {
        return [
            'bool' => [true],
            'signed int' => [-123],
            'unsigned int' => [1],
            'unsigned int as string' => ['1'],
            'unsigned numeric as string' => ['123'],
            'signed numeric as string' => ['-123'],
            'bool true as string' => ['true'],
            'bool false as string' => ['false'],
            'string' => ['foo'],
        ];
    }

    #[Test]
    #[DataProvider('falseDevModeDataProvider')]
    #[RunInSeparateProcess]
    public function testReactsOnDevModeConstantWhenFalse(int|bool|string $value): void
    {
        define('DEVMODE', $value);

        self::assertFalse($this->service->isDebugEnabled());
        self::assertFalse($this->service->isLoggingEnabled());
        self::assertFalse($this->service->isLoggingDetailsEnabled());
    }

    /**
     * @return array<string, array{int|bool|string}>
     */
    public static function falseDevModeDataProvider(): array
    {
        return [
            'bool' => [false],
            'unsigned int' => [0],
            'unsigned int as string' => ['0'],
            'empty string' => [''],
        ];
    }

    /**
     * @param non-empty-string $method
     */
    private function mockSystemSetting(SystemSetting $setting, string $method, mixed $value): void
    {
        $mockValue = $this->createConfiguredMock(Setting::class, [$method => $value]);

        $this->systemSettingRepositoryMock
            ->method('get')
            ->with($setting)
            ->willReturn($mockValue);
    }
}
