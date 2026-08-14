<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use ILIAS\ApiGateway\Activity\ActivityAction;
use ILIAS\ApiGateway\Activity\ActivityNamespace;
use ILIAS\ApiGateway\Activity\ActivityNamespaceFactory;
use ILIAS\ApiGateway\Activity\ActivityRoute;
use ILIAS\ApiGateway\Activity\ActivityRouteFactory;
use ILIAS\ApiGateway\Middleware\AuthenticationMiddleware;
use ILIAS\Component\Activities\Activity;
use PHPUnit\Framework\TestCase;

class ActivityRouteFactoryTest extends TestCase
{
    public function testCreatesRouteFromActivity(): void
    {
        $activity = $this->createMock(Activity::class);
        $namespaceFactory = $this->createMock(ActivityNamespaceFactory::class);
        $namespace = $this->createMock(ActivityNamespace::class);
        $inputFactory = $this->createMock(\ILIAS\UI\Component\Input\Factory::class);

        $namespaceFactory->expects(self::once())
            ->method('create')
            ->with($activity::class)
            ->willReturn($namespace);

        $expected = new ActivityRoute(
            $activity,
            new ActivityAction($activity, $inputFactory),
            $namespace,
            [
                AuthenticationMiddleware::class,
            ],
        );

        $factory = new ActivityRouteFactory(
            $namespaceFactory,
            $inputFactory,
        );

        $actual = $factory->create($activity);

        self::assertEquals($expected, $actual);

        self::assertInstanceOf(
            ActivityAction::class,
            $actual->getAction(),
        );
    }
}
