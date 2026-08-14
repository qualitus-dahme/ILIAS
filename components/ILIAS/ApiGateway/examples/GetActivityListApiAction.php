<?php

declare(strict_types=1);

namespace ILIAS\ApiGateway\Examples;

use ILIAS\ApiGateway\Activity\ActivityRouteFactory;
use ILIAS\ApiGateway\Routes\ApiRoute;
use ILIAS\Component\Activities\Activity;
use ILIAS\Component\Activities\Repository as ActivityRepository;

readonly class GetActivityListApiAction extends ApiRoute
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityRouteFactory $activityRouteFactory,
        private string $prefix,
    ) {
        $activities = $this->activityRepository->getActivitiesByName("/.*/");
        $activities = iterator_to_array($activities);
        $activityRoute = fn(Activity $activity): string => $this->prefix . $this->activityRouteFactory->create($activity)->getPath();
        $activityNamespace = fn(Activity $activity): string => get_class($activity);
        $activityNamespaces = array_map($activityNamespace, $activities);
        $activityRoutes = array_map($activityRoute, $activities);
        $routes = array_combine($activityNamespaces, $activityRoutes);

        parent::__construct(
            'GetAllActivities',
            '/activities',
            'GET',
            'List all available activities in the webservice.',
            fn(): array => $routes,
        );
    }
}
