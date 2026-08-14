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

use ILIAS\ApiGateway\Application\WebApp;
use ILIAS\ApiGateway\Logging\WebserviceLogger;
use ILIAS\ApiGateway\Logging\WebserviceLoggerFactory;
use ILIAS\ApiGateway\Middleware\MiddlewareRepository;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\ApiGateway\Webservice\WebserviceFactory;
use ILIAS\HTTP\Response\ResponseFactory;
use ilLoggerFactory;
use Psr\Log\LoggerInterface;

final readonly class WebAppFactory
{
    public function __construct(
        private HttpConfigFactory $httpConfigFactory,
        private HttpServiceFactory $httpServiceFactory,
        private WebserviceFactory $webserviceFactory,
        private RoutesRegistryFactory $routesRegistryFactory,
        private MiddlewareRepository $middlewareRepository,
        private ResponseFactory $responseFactory,
        private WebserviceLoggerFactory $loggerFactory
    ) {}

    public function create(ServiceProtocol $protocol): WebApp
    {
        $config = $this->httpConfigFactory->createWebConfig($protocol);
        $registry = $this->routesRegistryFactory->create();
        $webservice = $this->webserviceFactory->create($config);
        $logger = $this->loggerFactory->create($protocol->value);

        return new WebApp(
            $this->httpServiceFactory->createWebApplication(),
            $config,
            $registry,
            $this->middlewareRepository,
            $this->httpServiceFactory->createResponseHandler($webservice),
            $this->httpServiceFactory->createErrorHandler(
                $webservice,
                $config,
                $logger,
                $this->responseFactory
            ),
            $this->responseFactory,
            $logger
        );
    }
}
