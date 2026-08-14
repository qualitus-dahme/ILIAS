<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use ILIAS\ApiGateway\Middleware\AuthenticationMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(AuthenticationMiddleware::class)]
class AuthenticationMiddlewareTest extends TestCase
{
    private AuthenticationMiddleware $middleware;
    private Authentication&MockObject $authenticationService;
    private ServerRequestInterface&MockObject $request;
    private RequestHandlerInterface&MockObject $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->middleware = new AuthenticationMiddleware(
            $this->authenticationService = $this->createMock(Authentication::class)
        );

        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
    }

    public function testThrowsExceptionIfAuthorizationHeaderIsMissing(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('');

        $this->handler->expects(self::never())->method('handle');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authorization header missing or invalid.');
        $this->expectExceptionCode(401);

        $this->middleware->process($this->request, $this->handler);
    }

    #[DataProvider('invalidHeaderDataProvider')]
    public function testThrowsExceptionIfAuthorizationHeaderIsNotBearerToken(string $invalidHeader): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn($invalidHeader);

        $this->handler->expects(self::never())->method('handle');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authorization header missing or invalid.');
        $this->expectExceptionCode(401);

        $this->middleware->process($this->request, $this->handler);
    }

    #[DataProvider('emptyHeaderDataProvider')]
    public function testThrowsExceptionIfTokenIsEmpty(string $emptyHeader): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn($emptyHeader);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authorization token cannot be empty.');
        $this->expectExceptionCode(401);

        $this->middleware->process($this->request, $this->handler);
    }

    public function testThrowsExceptionIfTokenValidationFails(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer invalid-token');

        $this->authenticationService
            ->method('validateToken')
            ->with('invalid-token')
            ->willThrowException(new AuthenticationException('Token has expired.'));

        $this->handler->expects(self::never())->method('handle');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token has expired.');
        $this->expectExceptionCode(401);

        $this->middleware->process($this->request, $this->handler);
    }

    public function testAddsUserToRequestAndCallsNextHandlerOnSuccess(): void
    {
        $token = 'valid-token-string';
        $user = new AuthUser(1);
        $expected = $this->createMock(ResponseInterface::class);
        $requestWithUser = $this->createMock(ServerRequestInterface::class);

        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn("Bearer $token");
        $this->authenticationService->method('validateToken')
            ->with($token)
            ->willReturn($user);
        $this->request->method('withAttribute')
            ->with('authenticated_user', $user)
            ->willReturn($requestWithUser);
        $this->handler->method('handle')
            ->with($requestWithUser)
            ->willReturn($expected);

        $actual = $this->middleware->process($this->request, $this->handler);

        $this->assertSame($expected, $actual);
    }

    public function testItHandlesCaseInsensitiveBearerPrefix(): void
    {
        // Use 'bearer ' (lowercase) to test case-insensitivity
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn("bearer some-valid-token");
        $this->authenticationService->expects(self::once())->method('validateToken');
        $this->request->expects(self::once())->method('withAttribute');
        $this->handler->expects(self::once())->method('handle');

        $this->middleware->process($this->request, $this->handler);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function invalidHeaderDataProvider(): array
    {
        return [
            'Basic auth' => ['Basic MTIzNDU='],
            'Just a token' => ['some-token'],
            'Bearer without space' => ['Bearertoken'],
        ];
    }

    /**
     * @return array<string, array<string>>
     */
    public static function emptyHeaderDataProvider(): array
    {
        return [
            'Empty bearer' => ['Bearer '],
            'Bearer with spaces only' => ['Bearer    '],
        ];
    }
}
