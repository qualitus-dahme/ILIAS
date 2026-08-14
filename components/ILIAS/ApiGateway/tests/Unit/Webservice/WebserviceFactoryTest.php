<?php

declare(strict_types=1);

namespace Tests\Unit\Webservice;

use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use ILIAS\ApiGateway\Webservice\Infrastructure\RestWebservice;
use ILIAS\ApiGateway\Webservice\WebserviceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WebserviceFactoryTest extends TestCase
{
    private WebserviceFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new WebserviceFactory();
    }

    public function testCreatesRestWebservice(): void
    {
        $config = $this->createConfiguredMock(WebConfig::class, [
            'getProtocol' => ServiceProtocol::REST,
        ]);

        $expected = new RestWebservice($config);
        $actual = $this->factory->create($config);

        $this->assertInstanceOf(Webservice::class, $actual);
        $this->assertInstanceOf(RestWebservice::class, $actual);

        $this->assertEquals($expected, $actual);
    }

    /**
     * This test case is removed as soon as SOAP is supported
     */
    public function testThrowExceptionInCaseOfUnsupportedProtocol(): void
    {
        $protocol = ServiceProtocol::SOAP;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf("Unsupported service protocol: %s", $protocol->name)
        );

        $config = $this->createConfiguredMock(WebConfig::class, [
            'getProtocol' => $protocol,
        ]);

        $this->factory->create($config);
    }
}
