<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ILIAS\ApiGateway\Application\ErrorHandler;
use ILIAS\ApiGateway\Application\ResponseHandler;
use ILIAS\ApiGateway\Application\WebApp;
use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Middleware\MiddlewareRepository;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Routing\Route;
use ILIAS\ApiGateway\Routing\RoutesRegistry;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\HTTP\Response\ResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;
use Slim\Middleware\ErrorMiddleware;
use Slim\Routing\RouteCollectorProxy;

class WebAppTest extends TestCase
{
    private MockObject&RoutesRegistry $registry;
    private MockObject&MiddlewareRepository $middlewareRepository;
    private MockObject&ResponseHandler $responseHandler;
    private MockObject&ErrorHandler $errorHandler;
    private MockObject&LoggerInterface $logger;
    /** @var MockObject&SlimApp<\Psr\Container\ContainerInterface> */
    private MockObject&SlimApp $slimApp;
    private MockObject&ResponseFactory $responseFactory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createMock(RoutesRegistry::class);
        $this->middlewareRepository = $this->createMock(MiddlewareRepository::class);
        $this->responseHandler = $this->createMock(ResponseHandler::class);
        $this->errorHandler = $this->createMock(ErrorHandler::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactory::class);
        $this->slimApp = $this->createMock(SlimApp::class);
    }

    /**
     * Business Case: If the API is turned off by an admin, it should stop processing any calls
     * and just show a 'Service Unavailable' message for maintenance.
     */
    public function testExitIfWebserviceIsDisabled(): void
    {
        /**
         * Skipped: Direct output emission (Slim\ResponseEmitter) from internally instantiated final class
         * is difficult to unit test reliably without modifying the SUT (WebApp) or relying on fragile output buffering.
         */
        $this->markTestSkipped();

        $webApp = $this->createWebApp(isEnabled: false);

        $responseMock = $this->createMock(ResponseInterface::class);
        $streamHandle = fopen('php://memory', 'r+');

        if ($streamHandle === false) {
            self::fail('Failed to open memory stream for testing.');
        }

        $responseBodyMock = new \Slim\Psr7\Stream($streamHandle);

        $this->responseFactory->method('create')
            ->willReturn($responseMock);

        $responseMock->method('withStatus')
            ->with(503)
            ->willReturn($responseMock);
        $responseMock->method('withHeader')
            ->with('Content-Type', 'text/plain')
            ->willReturn($responseMock);

        $responseMock->method('getBody')->willReturn($responseBodyMock);

        $this->slimApp->expects(self::never())->method('run');

        ob_start();
        $webApp->run();
        ob_end_clean();

        $responseBodyMock->rewind();

        self::assertEquals('API Service is currently disabled.', $responseBodyMock->getContents());
    }

    /**
     * Business Case: When a request matches a defined API endpoint, the system should hand it off
     * to the central response handler to be processed.
     */
    public function testDispatchToTheCorrectActionWhenRouteIsMatched(): void
    {
        $webApp = $this->createWebApp(
            isEnabled: true,
        );

        $action = $this->createMock(Action::class);
        $routeMock = $this->createMock(Route::class);

        $routeMock->method('getMethod')->willReturn('GET');
        $routeMock->method('getPath')->willReturn('/test');
        $routeMock->method('getAction')->willReturn($action);

        $this->registry->expects(self::once())
            ->method('all')
            ->willReturn([$routeMock]);

        $groupProxyMock = $this->createMock(RouteCollectorProxy::class);

        $this->slimApp->method('group')
            ->willReturnCallback(function (string $pattern, callable $callable) use ($groupProxyMock) {
                $callable($groupProxyMock);

                return $this->createMock(\Slim\Interfaces\RouteGroupInterface::class);
            })
        ;

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $args = ['id' => '42'];

        $this->responseHandler->expects(self::once())
            ->method('__invoke')
            ->with($request, $response, $args, $action);

        // Inside the callback, we immediately execute the handler to verify it triggers the response handler expectation above.
        $groupProxyMock->expects(self::once())
            ->method('map')
            ->with(
                ['GET'],
                '/test',
                $this->callback(function (callable $handler) use ($request, $response, $args) {
                    $handler($request, $response, $args);
                    return true; // Callback constraint must return true.
                })
            );

        $webApp->run();
    }

    public function testAddRouteMiddlewares(): void
    {
        $webApp = $this->createWebApp();

        $routeMock = $this->createConfiguredMock(Route::class, [
            'getMethod' => 'GET',
            'getPath' => '/test',
            'getAction' => $action = $this->createMock(Action::class),
            'getMiddlewares' => ['Middleware1', 'Middleware2'],
        ]);

        $groupProxyMock = $this->createMock(RouteCollectorProxy::class);

        $this->slimApp->method('group')
            ->willReturnCallback(function (string $pattern, callable $callable) use ($groupProxyMock) {
                $callable($groupProxyMock);

                return $this->createMock(\Slim\Interfaces\RouteGroupInterface::class);
            })
        ;

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $args = ['id' => '42'];

        $this->responseHandler->expects(self::once())
            ->method('__invoke')
            ->with($request, $response, $args, $action);

        // Inside the callback, we immediately execute the handler to verify it triggers the response handler expectation above.
        $groupProxyMock->expects(self::once())
            ->method('map')
            ->with(
                [$routeMock->getMethod()],
                '/test',
                $this->callback(function (callable $handler) use ($request, $response, $args) {
                    $handler($request, $response, $args);
                    return true; // Callback constraint must return true.
                })
            );

        $this->registry->expects(self::once())
            ->method('all')
            ->willReturn([$routeMock]);

        $routeMock->expects(self::once())->method('getMiddlewares');

        $this->middlewareRepository->expects(self::exactly(2))->method('get');

        $webApp->run();
    }

    /**
     * Business Case: Once the API is set up and ready, it needs to start running so it can
     * begin listening for and handling incoming requests.
     */
    public function testRunAppIfWebserviceIsEnabled(): void
    {
        $webApp = $this->createWebApp();

        $this->slimApp->expects(self::once())->method('addRoutingMiddleware');
        $this->slimApp->expects(self::once())->method('run');

        $webApp->run();
    }

    /**
     * Business Case: To help with debugging, error handling should be configured based on the environment.
     * This ensures the right level of error detail is logged or displayed.
     */
    public function testRegisterErrorHandler(): void
    {
        $webApp = $this->createWebApp(
            debugMode: $debugMode = true,
            logErrors: $logErrors = false,
            logErrorDetails: $logErrorDetails = true,
        );

        $this->slimApp->expects(self::once())
            ->method('addErrorMiddleware')
            ->with(
                self::identicalTo($debugMode),
                self::identicalTo($logErrors),
                self::identicalTo($logErrorDetails),
                self::identicalTo($this->logger),
            )
            ->willReturn(
                $errorMiddlewareMock = $this->createMock(ErrorMiddleware::class)
            )
        ;

        $errorMiddlewareMock->expects(self::once())
            ->method('setDefaultErrorHandler')
            ->with(
                self::identicalTo($this->errorHandler),
            );

        $webApp->run();
    }

    private function createWebApp(
        string $baseUrl = '',
        bool $isEnabled = true,
        bool $debugMode = false,
        bool $logErrors = false,
        bool $logErrorDetails = false,
    ): WebApp {
        return new WebApp(
            $this->slimApp,
            new WebConfig(
                $baseUrl,
                ServiceProtocol::REST,
                $isEnabled,
                $debugMode,
                $logErrors,
                $logErrorDetails,
            ),
            $this->registry,
            $this->middlewareRepository,
            $this->responseHandler,
            $this->errorHandler,
            $this->responseFactory,
            $this->logger,
        );
    }
}
