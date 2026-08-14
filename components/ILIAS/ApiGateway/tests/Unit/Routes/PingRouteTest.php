<?php

declare(strict_types=1);

namespace Tests\Unit\Routes;

use ILIAS\ApiGateway\Routes\PingRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PingRoute::class)]
final class PingRouteTest extends TestCase
{
    private PingRoute $route;

    #[\Override]
    protected function setUp(): void
    {
        $this->route = new PingRoute();
    }

    public function testCreatesRouteWithResults(): void
    {
        /** @var array<string, mixed> */
        $actual = $this->route->getAction()([], null);

        self::assertSame('Ping', $this->route->getName());
        self::assertSame('/ping', $this->route->getPath());
        self::assertSame('GET', $this->route->getMethod());
        self::assertArrayHasKey('pong', $actual);
        self::assertTrue($actual['pong']);
    }

    public function testCreatesRouteWithParameters(): void
    {
        /** @var array<string, mixed> */
        $actual = $this->route->getAction()(['foo' => 'bar'], null);

        self::assertArrayHasKey('foo', $actual);
        self::assertSame('bar', $actual['foo']);
    }
}
