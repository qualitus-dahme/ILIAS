<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Configuration\Domain\Configuration;
use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpConfigFactory::class)]
final class HttpConfigFactoryTest extends TestCase
{
    private HttpConfigFactory $factory;
    private Configuration&MockObject $configuration;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new HttpConfigFactory(
            $this->configuration = $this->createMock(Configuration::class),
        );
    }

    public function testCreatesInstanceOfAuthConfig(): void
    {
        $configData = [
            'getClientId' => $clientId = 'foo',
            'getSecretKey' => $secretKey = 'bar',
            'getEncryption' => $encryption = 'aes256',
            'getHashing' => $hashing = 'sha256',
            'getAccessTokenExpiry' => $accessTokenExpiry = 3600,
            'getRefreshTokenExpiry' => $refreshTokenExpiry = 86400,
        ];

        $this->mockConfiguration($configData);

        $expected = new AuthConfig(
            $clientId,
            $secretKey,
            $encryption,
            $hashing,
            $accessTokenExpiry,
            $refreshTokenExpiry,
        );

        $actual = $this->factory->createAuthConfig();

        self::assertEquals($expected, $actual);
    }

    public function testCreatesInstanceOfWebConfig(): void
    {
        $protocol = ServiceProtocol::SOAP;

        $configData = [
            'getBaseUrl' => $baseUrl = 'foo',
            'isEnabled' => $isEnabled = true,
            'isDebugEnabled' => $isDebugEnabled = false,
            'isLoggingEnabled' => $isLoggingEnabled = true,
            'isLoggingDetailsEnabled' => $isLoggingDetailsEnabled = false,
        ];

        $this->mockConfiguration($configData);

        $expected = new WebConfig(
            $baseUrl,
            $protocol,
            $isEnabled,
            $isDebugEnabled,
            $isLoggingEnabled,
            $isLoggingDetailsEnabled,
        );

        $actual = $this->factory->createWebConfig($protocol);

        self::assertEquals($expected, $actual);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mockConfiguration(array $config): void
    {
        foreach ($config as $method => $value) {
            $this->configuration->method($method)->willReturn($value);
        }
    }
}
