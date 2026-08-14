<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Domain\Model;

use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WebConfigTest extends TestCase
{
    private WebConfig $model;
    private string $baseUrl;
    private ServiceProtocol $protocol;
    private bool $isEnabled;
    private bool $isDebugEnabled;
    private bool $isLoggingEnabled;
    private bool $isLoggingDetailsEnabled;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new WebConfig(
            $this->baseUrl = 'http://localhost',
            $this->protocol = ServiceProtocol::REST,
            $this->isEnabled = true,
            $this->isDebugEnabled = false,
            $this->isLoggingEnabled = true,
            $this->isLoggingDetailsEnabled = false,
        );
    }

    public function testHasAccessorToBaseUrl(): void
    {
        $actual = $this->model->getBaseUrl();

        self::assertSame($this->baseUrl, $actual);
    }

    public function testHasAccessorToBasePath(): void
    {
        $actual = $this->model->getBasePath();

        self::assertSame($this->protocol->value, $actual);
    }

    public function testHasAccessorToProtocol(): void
    {
        $actual = $this->model->getProtocol();

        self::assertSame($this->protocol, $actual);
    }

    public function testHasAccessorToIsEnabled(): void
    {
        $actual = $this->model->isEnabled();

        self::assertSame($this->isEnabled, $actual);
    }

    public function testHasAccessorToIsDebugEnabled(): void
    {
        $actual = $this->model->isDebugEnabled();

        self::assertSame($this->isDebugEnabled, $actual);
    }

    public function testHasAccessorToIsLogErrors(): void
    {
        $actual = $this->model->isLoggingEnabled();

        self::assertSame($this->isLoggingEnabled, $actual);
    }

    public function testHasAccessorToIsLogErrorDetails(): void
    {
        $actual = $this->model->isLoggingDetailsEnabled();

        self::assertSame($this->isLoggingDetailsEnabled, $actual);
    }
}
