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

use ILIAS\ApiGateway\Routing\Route;
use InvalidArgumentException;
use LogicException;

class RoutesRegistry
{
    /** @var array<string, \ILIAS\ApiGateway\Routing\Route> */
    private array $routes = [];

    /**
     * @param array<Route> $routes
     */
    public function __construct(array $routes)
    {
        array_walk($routes, [$this, 'register']);
    }

    /**
     * @return array<string, \ILIAS\ApiGateway\Routing\Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Registers a route for all of its specified HTTP methods.
     * Throws an exception if any method/path combination is already registered.
     */
    private function register(Route $route): void
    {
        $path = $route->getPath();
        $method = $route->getMethod();

        if (empty($method)) {
            throw new InvalidArgumentException("Cannot register a route with no HTTP methods for path '{$path}'.");
        }

        $key = self::getInternalKey($method, $path);

        if (isset($this->routes[$key])) {
            throw new LogicException("Duplicate route detected: Cannot re-register '{$key}'.");
        }

        $this->routes[$key] = $route;
    }

    private static function getInternalKey(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . $path;
    }
}
