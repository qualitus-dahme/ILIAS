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

namespace ILIAS\ApiGateway\Middleware;

use LogicException;
use Psr\Http\Server\MiddlewareInterface;

class MiddlewareRepository
{
    /** @var array<string, MiddlewareInterface> */
    private array $middlewares = [];

    /**
     * @param array<MiddlewareInterface> $middlewares
     */
    public function __construct(
        array $middlewares
    ) {
        $this->middlewares = $this->map($middlewares);
    }

    public function get(string $className): MiddlewareInterface
    {
        $middleware = $this->middlewares[$className] ?? null;

        /** @phpstan-ignore-next-line */
        if (!$middleware || false === $middleware instanceof MiddlewareInterface) {
            throw new LogicException(
                "Middleware '{$className}' requested is not registered."
            );
        }

        return $middleware;
    }

    /**
     * @param array<MiddlewareInterface> $middlewares
     *
     * @return array<string, MiddlewareInterface>
     */
    private function map(array $middlewares): array
    {
        return array_combine(
            array_map(\get_class(...), $middlewares),
            $middlewares
        );
    }
}
