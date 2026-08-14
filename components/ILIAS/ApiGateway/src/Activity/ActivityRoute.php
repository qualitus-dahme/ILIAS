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

namespace ILIAS\ApiGateway\Activity;

use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Routing\HttpMethod;
use ILIAS\ApiGateway\Routing\Route;
use ILIAS\Component\Activities\Activity;
use ILIAS\Component\Activities\ActivityType;
use ILIAS\Component\Activities\ObjectActivity;
use Override;

readonly class ActivityRoute implements Route
{
    /**
     * @param array<string> $middlewares
     */
    public function __construct(
        private Activity $activity,
        private ActivityAction $action,
        private ActivityNamespace $namespace,
        private array $middlewares,
    ) {
    }

    #[Override]
    public function getPath(): string
    {
        $path = $this->namespace->getPath();

        if ($this->activity instanceof ObjectActivity) {
            $path .= '/{id}';
        }

        return $path;
    }

    #[Override]
    public function getMethod(): string
    {
        return match ($this->activity->getType()) {
            ActivityType::Command => HttpMethod::POST->value,
            ActivityType::Query => HttpMethod::GET->value,
        };
    }

    #[Override]
    public function getAction(): Action
    {
        return $this->action;
    }

    #[Override]
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
