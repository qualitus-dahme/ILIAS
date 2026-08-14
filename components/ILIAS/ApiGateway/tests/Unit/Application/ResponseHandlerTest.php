<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ILIAS\ApiGateway\Application\ResponseHandler;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Webservice\Domain\Model\Payload;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;

class ResponseHandlerTest extends TestCase
{
    private ResponseHandler $handler;
    private Webservice&MockObject $webserviceMock;
    private Request&MockObject $requestMock;
    private Response&MockObject $responseMock;
    private StreamInterface&MockObject $streamMock;

    #[\Override]
    protected function setUp(): void
    {
        $this->webserviceMock = $this->createMock(Webservice::class);
        $this->requestMock = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->streamMock = $this->createMock(StreamInterface::class);

        $this->responseMock->method('getBody')->willReturn($this->streamMock);

        $this->handler = new ResponseHandler($this->webserviceMock);
    }

    public function testDispatchSuccessfullyHandlesRequestAndWritesResponse(): void
    {
        $queryParams = ['queryParam' => 'queryValue', 'routeParam' => 'routeValue'];
        $routeArgs = ['routeParam' => 'routeValue-override'];
        $mergedParams = array_merge($queryParams, $routeArgs);
        $handlerResponseBody = ['handler' => 'response'];
        $serviceResponseBody = '{"service":"response"}';
        $userMock = $this->createMock(AuthUser::class);

        $finalRequest = $this->createConfiguredMock(Request::class, [
            'getQueryParams' => $queryParams,
            'getParsedBody' => [],
            'getBody' => $this->streamMock,
            'getHeaderLine' => ''
        ]);

        $this->requestMock->expects(self::once())
            ->method('getAttribute')
            ->with(self::identicalTo('authenticated_user'))
            ->willReturn($userMock);
        $this->requestMock->expects(self::once())
            ->method('withoutAttribute')
            ->with(self::identicalTo('authenticated_user'))
            ->willReturn($finalRequest);

        $actionMock = $this->createMock(Action::class);

        $actionMock->method('__invoke')
            ->with(
                $this->callback(function (array $params) use ($mergedParams): bool {
                    self::assertEquals($mergedParams, $params);

                    return true;
                }),
                self::identicalTo($userMock)
            )
            ->willReturn($handlerResponseBody);

        $originalPayload = new Payload(data: $handlerResponseBody);
        $payload = new Payload(
            data: $handlerResponseBody,
            headers: [
                'foo' => 'bar',
            ],
            body: $serviceResponseBody,
        );

        $this->webserviceMock->method('handle')
            ->with(
                self::equalTo($originalPayload),
            )
            ->willReturn($payload);

        $this->streamMock->expects(self::once())
            ->method('write')
            ->with($serviceResponseBody);

        $this->responseMock
            ->expects(self::once())
            ->method('withHeader')
            ->with(
                self::identicalTo('foo'),
                self::identicalTo('bar'),
            )
            ->willReturn($this->responseMock)
        ;

        $response = ($this->handler)(
            $this->requestMock,
            $this->responseMock,
            $routeArgs,
            $actionMock,
        );

        self::assertSame($this->responseMock, $response);
    }

    public function testHandlesRequestWithJsonObjectBody(): void
    {
        $queryParams = ['query' => 'q_val'];
        $bodyParams = ['body' => 'b_val'];
        $routeArgs = ['route' => 'r_val'];
        $expectedFinalParams = array_merge($queryParams, $bodyParams, $routeArgs);

        $bodyContents = json_encode($bodyParams);

        $this->requestMock->method('getQueryParams')->willReturn($queryParams);
        $this->requestMock->method('getParsedBody')->willReturn([]); // parsed body should be empty, as we parse json body manually
        $this->requestMock->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $this->requestMock->method('getBody')->willReturn($this->streamMock);
        $this->streamMock->method('isSeekable')->willReturn(true);
        $this->streamMock->expects(self::once())->method('rewind');
        $this->streamMock->method('getContents')->willReturn($bodyContents);
        $this->requestMock->method('getAttribute')->with('authenticated_user')->willReturn(null);
        $this->requestMock->method('withoutAttribute')->with('authenticated_user')->willReturn($this->requestMock);

        $actionMock = $this->createMock(Action::class);
        $actionMock->expects(self::once())
            ->method('__invoke')
            ->with($expectedFinalParams, null);

        $this->webserviceMock->method('handle')->willReturn(new Payload());
        $this->responseMock->method('withHeader')->willReturn($this->responseMock);

        ($this->handler)(
            $this->requestMock,
            $this->responseMock,
            $routeArgs,
            $actionMock
        );
    }

    public function testIgnoresJsonScalarBody(): void
    {
        // A scalar in the body should be ignored by the array_merge
        $queryParams = ['query' => 'q_val'];
        $routeArgs = ['route' => 'r_val'];
        $expectedFinalParams = array_merge($queryParams, $routeArgs); // Note: body params are NOT merged

        $this->requestMock->method('getQueryParams')->willReturn($queryParams);
        $this->requestMock->method('getParsedBody')->willReturn([]);
        $this->requestMock->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $this->requestMock->method('getBody')->willReturn($this->streamMock);
        $this->streamMock->method('isSeekable')->willReturn(true);
        $this->streamMock->expects(self::once())->method('rewind');
        $this->streamMock->method('getContents')->willReturn('"this is a scalar string"'); // A valid JSON scalar

        $this->requestMock->method('getAttribute')->with('authenticated_user')->willReturn(null);
        $this->requestMock->method('withoutAttribute')->with('authenticated_user')->willReturn($this->requestMock);

        $actionMock = $this->createMock(Action::class);
        $actionMock->expects(self::once())
            ->method('__invoke')
            ->with($expectedFinalParams, null);

        $this->webserviceMock->method('handle')->willReturn(new Payload());
        $this->responseMock->method('withHeader')->willReturn($this->responseMock);

        ($this->handler)(
            $this->requestMock,
            $this->responseMock,
            $routeArgs,
            $actionMock
        );
    }

    public function testIgnoresMalformedJsonBody(): void
    {
        // Malformed JSON should be ignored
        $queryParams = ['query' => 'q_val'];
        $routeArgs = ['route' => 'r_val'];
        $expectedFinalParams = array_merge($queryParams, $routeArgs); // Note: body params are NOT merged

        $this->requestMock->method('getQueryParams')->willReturn($queryParams);
        $this->requestMock->method('getParsedBody')->willReturn([]);
        $this->requestMock->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $this->requestMock->method('getBody')->willReturn($this->streamMock);
        $this->streamMock->method('isSeekable')->willReturn(true);
        $this->streamMock->expects(self::once())->method('rewind');
        $this->streamMock->method('getContents')->willReturn('{"malformed": "json"'); // Malformed JSON

        $this->requestMock->method('getAttribute')->with('authenticated_user')->willReturn(null);
        $this->requestMock->method('withoutAttribute')->with('authenticated_user')->willReturn($this->requestMock);

        $actionMock = $this->createMock(Action::class);
        $actionMock->expects(self::once())
            ->method('__invoke')
            ->with($expectedFinalParams, null);

        $this->webserviceMock->method('handle')->willReturn(new Payload());
        $this->responseMock->method('withHeader')->willReturn($this->responseMock);

        ($this->handler)(
            $this->requestMock,
            $this->responseMock,
            $routeArgs,
            $actionMock
        );
    }

    public function testHandlesRequestWithObjectParsedBody(): void
    {
        $queryParams = ['query' => 'q_val'];
        $bodyParams = (object)['body' => 'b_val'];
        $routeArgs = ['route' => 'r_val'];
        $expectedFinalParams = array_merge($queryParams, (array)$bodyParams, $routeArgs);

        $this->requestMock->method('getQueryParams')->willReturn($queryParams);
        $this->requestMock->method('getParsedBody')->willReturn($bodyParams);
        $this->requestMock->method('getHeaderLine')->with('Content-Type')->willReturn('');
        $this->requestMock->method('getAttribute')->with('authenticated_user')->willReturn(null);
        $this->requestMock->method('withoutAttribute')->with('authenticated_user')->willReturn($this->requestMock);
        // We must mock getBody() because it is used for JSON content type, and without mocking it might be called
        $this->requestMock->method('getBody')->willReturn($this->streamMock);

        $actionMock = $this->createMock(Action::class);
        $actionMock->expects(self::once())
            ->method('__invoke')
            ->with($expectedFinalParams, null);

        $this->webserviceMock->method('handle')->willReturn(new Payload());
        $this->responseMock->method('withHeader')->willReturn($this->responseMock);

        ($this->handler)(
            $this->requestMock,
            $this->responseMock,
            $routeArgs,
            $actionMock
        );
    }

    public function testHandlesRequestWithNullParsedBody(): void
    {
        $queryParams = ['query' => 'q_val'];
        $routeArgs = ['route' => 'r_val'];
        $expectedFinalParams = array_merge($queryParams, $routeArgs);

        $this->requestMock->method('getQueryParams')->willReturn($queryParams);
        $this->requestMock->method('getParsedBody')->willReturn(null);
        $this->requestMock->method('getHeaderLine')->with('Content-Type')->willReturn('');
        $this->requestMock->method('getAttribute')->with('authenticated_user')->willReturn(null);
        $this->requestMock->method('withoutAttribute')->with('authenticated_user')->willReturn($this->requestMock);
        $this->requestMock->method('getBody')->willReturn($this->streamMock);

        $actionMock = $this->createMock(Action::class);
        $actionMock->expects(self::once())
            ->method('__invoke')
            ->with($expectedFinalParams, null);

        $this->webserviceMock->method('handle')->willReturn(new Payload());
        $this->responseMock->method('withHeader')->willReturn($this->responseMock);

        ($this->handler)(
            $this->requestMock,
            $this->responseMock,
            $routeArgs,
            $actionMock
        );
    }
}
