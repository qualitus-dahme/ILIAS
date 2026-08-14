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

namespace ILIAS\ApiGateway\Routes;

use Closure;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Routing\Route;
use Override;

readonly class ApiRoute implements Route
{
    private Action $actionInstance;

    /**
     * @param string[] $middlewares
     */
    public function __construct(
        private string $name,
        private string $path,
        private string $method,
        private string $description,
        Closure $action,
        private array $middlewares = [],
    ) {
        $this->actionInstance = new readonly class ($action) implements Action {
            public function __construct(private Closure $handle)
            {
            }

            #[Override]
            public function __invoke(array $params, ?AuthUser $user)
            {
                return ($this->handle)($params, $user);
            }
        };
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    #[Override]
    public function getPath(): string
    {
        return $this->path;
    }

    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[Override]
    public function getAction(): Action
    {
        return $this->actionInstance;
    }

    #[Override]
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
