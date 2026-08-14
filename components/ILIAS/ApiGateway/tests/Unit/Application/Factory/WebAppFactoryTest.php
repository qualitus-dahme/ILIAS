<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use ILIAS\ApiGateway\Application\ErrorHandler;
use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Application\Factory\HttpServiceFactory;
use ILIAS\ApiGateway\Application\Factory\RoutesRegistryFactory;
use ILIAS\ApiGateway\Application\Factory\WebAppFactory;
use ILIAS\ApiGateway\Application\ResponseHandler;
use ILIAS\ApiGateway\Application\WebApp;
use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Logging\WebserviceLoggerFactory;
use ILIAS\ApiGateway\Middleware\MiddlewareRepository;
use ILIAS\ApiGateway\Routing\RoutesRegistry;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use ILIAS\ApiGateway\Webservice\WebserviceFactory;
use ILIAS\HTTP\Response\ResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;

final class WebAppFactoryTest extends TestCase
{
    private WebAppFactory $factory;
    private HttpConfigFactory&MockObject $httpConfigFactory;
    private HttpServiceFactory&MockObject $httpServiceFactory;
    private WebserviceFactory&MockObject $webserviceFactory;
    private RoutesRegistryFactory&MockObject $routesRegistryFactory;
    private MiddlewareRepository&MockObject $middlewareRepository;
    private ResponseFactory&MockObject $responseFactory;
    private WebserviceLoggerFactory&MockObject $loggerFactory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->httpConfigFactory = $this->createMock(HttpConfigFactory::class);
        $this->httpServiceFactory = $this->createMock(HttpServiceFactory::class);
        $this->webserviceFactory = $this->createMock(WebserviceFactory::class);
        $this->routesRegistryFactory = $this->createMock(RoutesRegistryFactory::class);
        $this->middlewareRepository = $this->createMock(MiddlewareRepository::class);
        $this->responseFactory = $this->createMock(ResponseFactory::class);
        $this->loggerFactory = $this->createMock(WebserviceLoggerFactory::class);

        $this->factory = new WebAppFactory(
            $this->httpConfigFactory,
            $this->httpServiceFactory,
            $this->webserviceFactory,
            $this->routesRegistryFactory,
            $this->middlewareRepository,
            $this->responseFactory,
            $this->loggerFactory,
        );
    }

    public function testUsesComponentsToCreateInstanceOfWebApp(): void
    {
        $protocol = ServiceProtocol::SOAP;
        $webConfigMock = $this->createMock(WebConfig::class);
        $webserviceMock = $this->createMock(Webservice::class);
        $routesRegistryMock = $this->createMock(RoutesRegistry::class);
        $responseHandlerMock = $this->createMock(ResponseHandler::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $errorHandlerMock = $this->createMock(ErrorHandler::class);
        /** @var SlimApp<\Psr\Container\ContainerInterface>&MockObject */
        $applicationMock = $this->createMock(SlimApp::class);

        $this->httpConfigFactory->expects(self::once())
            ->method('createWebConfig')
            ->with(self::identicalTo($protocol))
            ->willReturn($webConfigMock);

        $this->httpServiceFactory->expects(self::once())
            ->method('createResponseHandler')
            ->with(self::identicalTo($webserviceMock))
            ->willReturn($responseHandlerMock);

        $this->routesRegistryFactory->expects(self::once())
            ->method('create')
            ->willReturn($routesRegistryMock);

        $this->webserviceFactory->expects(self::once())
            ->method('create')
            ->with(self::identicalTo($webConfigMock))
            ->willReturn($webserviceMock);

        $this->loggerFactory->expects(self::once())
            ->method('create')
            ->with(self::identicalTo($protocol->value))
            ->willReturn($loggerMock);

        $this->httpServiceFactory->expects(self::once())
            ->method('createErrorHandler')
            ->with(
                self::identicalTo($webserviceMock),
                self::identicalTo($webConfigMock),
                self::identicalTo($loggerMock),
                self::identicalTo($this->responseFactory),
            )
            ->willReturn($errorHandlerMock);

        $this->httpServiceFactory->expects(self::once())
            ->method('createWebApplication')
            ->willReturn($applicationMock);

        $expected = new WebApp(
            $applicationMock,
            $webConfigMock,
            $routesRegistryMock,
            $this->middlewareRepository,
            $responseHandlerMock,
            $errorHandlerMock,
            $this->responseFactory,
            $loggerMock,
        );

        $actual = $this->factory->create($protocol);

        self::assertEquals($expected, $actual);
    }
}
