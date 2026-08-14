<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\ApiGateway\Application\Factory;

use ILIAS\ApiGateway\Activity\ActivityRouteFactory;
use ILIAS\ApiGateway\Routing\RoutesRegistry;
use ILIAS\ApiGateway\Routing\RouteRepository;
use ILIAS\Component\Activities\Repository as ActivityRepository;

readonly class RoutesRegistryFactory
{
    public function __construct(
        private RouteRepository $routeRepository,
        private ActivityRepository $activityRepository,
        private ActivityRouteFactory $activityRouteFactory,
    ) {}

    public function create(): RoutesRegistry
    {
        return new RoutesRegistry([
            ...$this->routeRepository->getAll(),
            // gather all activities and convert to ActivityRoute
            ...array_map(
                $this->activityRouteFactory->create(...),
                iterator_to_array(
                    $this->activityRepository->getActivitiesByName("/.*/")
                )
            ),
        ]);
    }
}
