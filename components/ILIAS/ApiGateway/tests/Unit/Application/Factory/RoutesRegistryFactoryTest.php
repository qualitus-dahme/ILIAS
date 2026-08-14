<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use ArrayIterator;
use ILIAS\ApiGateway\Activity\ActivityRoute;
use ILIAS\ApiGateway\Activity\ActivityRouteFactory;
use ILIAS\ApiGateway\Application\Factory\RoutesRegistryFactory;
use ILIAS\ApiGateway\Routing\Route;
use ILIAS\ApiGateway\Routing\RoutesRegistry;
use ILIAS\ApiGateway\Routing\RouteRepository;
use ILIAS\Component\Activities\Activity;
use ILIAS\Component\Activities\Repository as ActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutesRegistryFactory::class)]

class RoutesRegistryFactoryTest extends TestCase
{
    private RoutesRegistryFactory $factory;
    private MockObject&RouteRepository $routeRepository;
    private MockObject&ActivityRepository $activityRepository;
    private MockObject&ActivityRouteFactory $activityRouteFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new RoutesRegistryFactory(
            $this->routeRepository = $this->createMock(RouteRepository::class),
            $this->activityRepository = $this->createMock(ActivityRepository::class),
            $this->activityRouteFactory = $this->createMock(ActivityRouteFactory::class),
        );
    }

    public function testCollectsRoutes(): void
    {
        $activity = $this->createMock(Activity::class);
        $routes = [
            $route1 = $this->createConfiguredMock(Route::class, [
                'getPath' => '/foo',
                'getMethod' => 'get',
            ]),
            $route2 = $this->createConfiguredMock(ActivityRoute::class, [
                'getPath' => '/bar',
                'getMethod' => 'post',
            ]),
        ];
        $expected = new RoutesRegistry($routes);

        $this->routeRepository->expects(self::once())->method('getAll')->willReturn(new ArrayIterator([$route1]));
        $this->activityRepository->expects(self::once())->method('getActivitiesByName')->willReturn(new ArrayIterator([$activity]));
        $this->activityRouteFactory->expects(self::once())->method('create')->with(self::identicalTo($activity))->willReturn($route2);

        $actual = $this->factory->create();

        self::assertEquals($expected, $actual);
    }
}
