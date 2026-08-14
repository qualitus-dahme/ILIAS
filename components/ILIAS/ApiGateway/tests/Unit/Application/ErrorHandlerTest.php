<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Exception;
use ILIAS\ApiGateway\Application\ErrorHandler;
use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\ApiGateway\Webservice\Domain\Model\Payload;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use ILIAS\HTTP\Response\ResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

final class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $handler;
    private string $payloadBody = 'Specific Message';
    private MockObject&Webservice $webservice;
    private MockObject&LoggerInterface $logger;
    private MockObject&ResponseFactory $responseFactory;
    private MockObject&ServerRequestInterface $request;
    private MockObject&ResponseInterface $response;
    private MockObject&StreamInterface $stream;

    #[\Override]
    protected function setUp(): void
    {
        $this->webservice = $this->createMock(Webservice::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactory::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream = $this->createMock(StreamInterface::class);

        $this->responseFactory->method('create')->willReturn($this->response);
        $this->response->method('withHeader')->willReturn($this->response);
        $this->response->method('getBody')->willReturn($this->stream);
        $this->webservice->method('handleError')->willReturn(
            new Payload(
                headers: ['foo' => 'bar'],
                body: $this->payloadBody,
            ),
        );

        $this->handler = $this->createErrorHandler();
    }

    public function testReturnsNumericExceptionCodeCorrectly(): void
    {
        $exception = new class () extends Exception {
            protected $code = '503'; // @phpstan-ignore-line
        };

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(503)
            ->willReturn($this->response);

        ($this->handler)($this->request, $exception);
    }

    public function testReturns500IfExceptionCodeIsNotNumeric(): void
    {
        $exception = new class () extends Exception {
            protected $code = 'foo'; // @phpstan-ignore-line
        };

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(500)
            ->willReturn($this->response);

        ($this->handler)($this->request, $exception);
    }

    public function testReturns500IfExceptionCodeIsNotAValidHttpErrorCode(): void
    {
        $exception = new Exception('Test Exception', 0);

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(500)
            ->willReturn($this->response);

        $response = ($this->handler)($this->request, $exception);

        self::assertSame($this->response, $response);
    }

    public function testUsesExceptionCodeAsStatusInCaseOfA4xxError(): void
    {
        $exception = new Exception('Not Found', 404);

        $expected = $this->createMock(ResponseInterface::class);

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(404)
            ->willReturn($expected);

        $response = ($this->handler)($this->request, $exception);

        self::assertSame($expected, $response);
    }

    public function testUsesExceptionCodeAsStatusInCaseOfA5xxError(): void
    {
        $exception = new Exception('Service Unavailable', 503);

        $expected = $this->createMock(ResponseInterface::class);

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(503)
            ->willReturn($expected);

        $response = ($this->handler)($this->request, $exception);

        self::assertSame($expected, $response);
    }

    public function testWritesPayloadFromWebserviceToResponseBody(): void
    {
        $exception = new Exception('Any Exception', 200);

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(500)
            ->willReturn($this->response);

        $this->stream->expects(self::once())
            ->method('write')
            ->with($this->payloadBody);

        ($this->handler)($this->request, $exception);
    }

    public function testAppendsPayloadHeadersToResponseHeaders(): void
    {
        $exception = new Exception('Any Exception', 500);

        $this->response->expects(self::once())
            ->method('withStatus')
            ->with(500)
            ->willReturn($this->response);

        $this->response->expects(self::once())
            ->method('withHeader')
            ->with(
                self::identicalTo('foo'),
                self::identicalTo('bar'),
            )
            ->willReturn($this->response);

        ($this->handler)($this->request, $exception);
    }

    public function testDoesNotLogIfLoggingIsDisabled(): void
    {
        $exception = new Exception('Any Exception');

        $this->logger->expects(static::never())->method('error');

        ($this->handler)($this->request, $exception);
    }

    public function testLogsOnlyMessageIfDetailsAreDisabled(): void
    {
        $exception = new Exception($message = 'A specific error occurred');

        $handler = $this->createErrorHandler(
            logErrors: true,
        );

        $this->logger->expects(self::once())
            ->method('error')
            ->with($message);

        $handler($this->request, $exception);
    }

    public function testLogsMessageAndStacktraceIfDetailsAreEnabled(): void
    {
        $exception = new Exception($message = 'A specific error occurred');

        $handler = $this->createErrorHandler(
            logErrors: true,
            logErrorDetails: true,
        );

        $expectedLog = "{$message}\nStack trace:\n" . (string) $exception;

        $this->logger->expects(self::once())
            ->method('error')
            ->with($expectedLog);

        $handler($this->request, $exception);
    }

    private function createErrorHandler(
        bool $debugMode = false,
        bool $logErrors = false,
        bool $logErrorDetails = false,
    ): ErrorHandler {
        return new ErrorHandler(
            $this->webservice,
            new WebConfig(
                'http://localhost',
                ServiceProtocol::REST,
                true,
                $debugMode,
                $logErrors,
                $logErrorDetails,
            ),
            $this->logger,
            $this->responseFactory,
        );
    }
}
