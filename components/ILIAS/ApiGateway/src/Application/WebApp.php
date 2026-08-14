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

namespace ILIAS\ApiGateway\Application;

use Closure;
use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Middleware\MiddlewareRepository;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Routing\RoutesRegistry;
use ILIAS\HTTP\Response\ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;
use Slim\ResponseEmitter;

/**
 * The core idea is to have this class acts as an orchestrator that wires components together.
 * The actual handling of requests is delegated to specialized classes.
 * The Webservice is treated as a pure "formatting" or "view" layer, completely decoupled from the application's request/response lifecycle.
 */
final readonly class WebApp
{
    /**
     * @param SlimApp<\Psr\Container\ContainerInterface> $application
     */
    public function __construct(
        private SlimApp $application,
        private WebConfig $configuration,
        private RoutesRegistry $registry,
        private MiddlewareRepository $middlewareRepository,
        private ResponseHandler $responseHandler,
        private ErrorHandler $errorHandler,
        private ResponseFactory $responseFactory,
        private LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        if (!$this->configuration->isEnabled()) {
            $response = $this->responseFactory->create();

            $response = $response->withStatus(503)->withHeader('Content-Type', 'text/plain');
            $response->getBody()->write('API Service is currently disabled.');

            (new ResponseEmitter())->emit($response);

            return;
        }

        $this->registerMiddlewares();
        $this->registerRoutes();

        global $DIC;

        $request = $DIC ? $DIC['http']?->raw()?->request() : null;

        $this->application->run($request);
    }

    private function registerMiddlewares(): void
    {
        /**
         * The routing middleware should be added earlier than the error middleware.
         * Otherwise exceptions thrown from it will not be handled by the middleware.
         */
        $this->application->addRoutingMiddleware();

        // register default middlewares
        // register service middlewares

        /**
         * The error middleware should be added last. It will not handle any exceptions/errors for middleware added after it.
         */
        $this->registerErrorHandler();
    }

    private function registerErrorHandler(): void
    {
        $errorMiddleware = $this->application->addErrorMiddleware(
            $this->configuration->isDebugEnabled(),
            $this->configuration->isLoggingEnabled(),
            $this->configuration->isLoggingDetailsEnabled(),
            $this->logger,
        );

        $errorMiddleware->setDefaultErrorHandler($this->errorHandler);
    }

    private function registerRoutes(): void
    {
        // group instead of using $this->app->setBasePath()
        $this->application->group(
            $this->getBasePath(),
            function (\Slim\Routing\RouteCollectorProxy $group): void {
                foreach ($this->registry->all() as $route) {
                    $slimRoute = $group
                        ->map(
                            [$route->getMethod()],
                            $route->getPath(),
                            $this->createRouteHandler($route->getAction()),
                        );

                    foreach ($route->getMiddlewares() as $middlewareClassname) {
                        $middleware = $this->middlewareRepository->get($middlewareClassname);
                        $slimRoute->add($middleware);
                    }
                }
            }
        );
    }

    private function createRouteHandler(Action $action): Closure
    {
        return fn(
            Request $request,
            Response $response,
            array $args,
        ): Response => ($this->responseHandler)(
            $request,
            $response,
            $args,
            $action,
        );
    }

    private function getBasePath(): string
    {
        return '/' . trim($this->configuration->getBasePath(), '/');
    }
}
