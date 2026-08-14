<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use ILIAS\ApiGateway\Routing\Route;
use ILIAS\ApiGateway\Routing\RouteRepository;
use PHPUnit\Framework\TestCase;

final class RouteRepositoryTest extends TestCase
{
    public function testGetsAllRoutes(): void
    {
        $route1 = $this->createConfiguredMock(Route::class, [
            'getPath' => '/api/route1',
        ]);
        $route2 = $this->createConfiguredMock(Route::class, [
            'getPath' => '/api/route2',
        ]);

        $repository = new RouteRepository([$route1, $route2]);

        $expected = [
            '/api/route1' => $route1,
            '/api/route2' => $route2,
        ];

        $actual = iterator_to_array($repository->getAll());

        self::assertSame($expected, $actual);
    }

    public function testIgnoresInvalidRoutes(): void
    {
        $validRoute = $this->createConfiguredMock(Route::class, [
            'getPath' => '/api/valid',
        ]);

        $invalidObject = new \stdClass();
        $invalidString = 'not a route';
        $invalidNull = null;

        /** @psalm-suppress InvalidArgument */
        // @phpstan-ignore-next-line
        $repository = new RouteRepository([
            $validRoute,
            $invalidObject,
            $invalidString,
            $invalidNull,
        ]);

        $expected = [
            '/api/valid' => $validRoute,
        ];

        $actual = iterator_to_array($repository->getAll());

        self::assertSame($expected, $actual);
    }
}
