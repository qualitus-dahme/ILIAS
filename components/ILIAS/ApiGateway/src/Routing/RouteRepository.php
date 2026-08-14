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

namespace ILIAS\ApiGateway\Routing;

use Iterator;

readonly class RouteRepository
{
    /**
     * @param \ILIAS\ApiGateway\Routing\Route[] $routes
     */
    public function __construct(
        private array $routes,
    ) {
    }

    /**
     * @return Iterator<string, \ILIAS\ApiGateway\Routing\Route>
     */
    public function getAll(): Iterator
    {
        foreach ($this->routes as $route) {
            /** @phpstan-ignore-next-line */
            if ($route instanceof Route) {
                yield $route->getPath() => $route;
            }
        }
    }
}
