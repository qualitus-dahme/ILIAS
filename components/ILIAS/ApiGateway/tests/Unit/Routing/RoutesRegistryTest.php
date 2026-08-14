<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use ILIAS\ApiGateway\Routing\Route;
use ILIAS\ApiGateway\Routing\RoutesRegistry;
use PHPUnit\Framework\TestCase;
use LogicException;
use InvalidArgumentException;

final class RoutesRegistryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testRegistersAndRetrievesRoute(): void
    {
        $route = $this->createConfiguredMock(Route::class, [
            'getPath' => $path = '/test',
            'getMethod' => $method = 'GET',
        ]);

        $key = "$method $path";

        $registry = new RoutesRegistry([$route]);
        $routes = $registry->all();

        self::assertArrayHasKey($key, $routes);
        self::assertSame($route, $routes[$key]);
    }

    public function testHandlesHttpMethodsCaseInsensitively(): void
    {
        $route = $this->createConfiguredMock(Route::class, [
            'getPath' => $path = '/test',
            'getMethod' => $method = 'Post',
        ]);

        $key = "POST $path";

        $registry = new RoutesRegistry([$route]);
        $routes = $registry->all();

        self::assertArrayHasKey($key, $routes);
        self::assertSame($route, $routes[$key]);
    }

    public function testThrowsExceptionForRouteWithEmptyMethod(): void
    {
        $route = $this->createConfiguredMock(Route::class, [
            'getPath' => $path = '/no-methods',
            'getMethod' => '',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot register a route with no HTTP methods for path '{$path}'.");

        new RoutesRegistry([$route]);
    }

    public function testThrowsExceptionInCaseOfDuplicateRoute(): void
    {
        $route1 = $this->createConfiguredMock(Route::class, [
            'getPath' => $path = '/duplicate',
            'getMethod' => $method = 'GET',
        ]);
        $route2 = $this->createConfiguredMock(Route::class, [
            'getPath' => $path,
            'getMethod' => $method,
        ]);
        $route3 = $this->createConfiguredMock(Route::class, [
            'getPath' => $path,
            'getMethod' => 'POST',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Duplicate route detected: Cannot re-register 'GET {$path}'.");

        new RoutesRegistry([$route1, $route2, $route3]);
    }
}
