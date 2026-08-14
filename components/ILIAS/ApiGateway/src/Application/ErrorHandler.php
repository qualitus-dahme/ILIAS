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

use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use ILIAS\HTTP\Response\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * This is the global, application-wide safety net, plugged into Slim's ErrorMiddleware.
 */
readonly class ErrorHandler
{
    public function __construct(
        private Webservice $service,
        private WebConfig $config,
        private LoggerInterface $logger,
        private ResponseFactory $responseFactory,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
    ): ResponseInterface {
        if ($this->config->isLoggingEnabled()) {
            $logMessage = $exception->getMessage();
            if ($this->config->isLoggingDetailsEnabled()) {
                $logMessage .= "\nStack trace:\n" . (string) $exception;
            }

            $this->logger->error($logMessage);
        }

        $statusCode = is_numeric($exception->getCode()) ? (int) $exception->getCode() : 500;
        $statusCode = $statusCode >= 400 && $statusCode < 600
            ? $statusCode
            : 500;

        $responsePayload = $this->service->handleError($exception);

        $response = $this->responseFactory->create();

        foreach ($responsePayload->getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response = $response->withStatus($statusCode);

        $response->getBody()->write($responsePayload->getBody());

        return $response;
    }
}
