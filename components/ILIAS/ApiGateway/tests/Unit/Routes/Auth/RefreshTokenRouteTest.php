<?php

declare(strict_types=1);

namespace Tests\Unit\Routes\Auth;

use ILIAS\ApiGateway\Auth\Domain\Model\TokenSet;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use ILIAS\ApiGateway\Routes\Auth\RefreshTokenRoute;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshTokenRoute::class)]
class RefreshTokenRouteTest extends TestCase
{
    private RefreshTokenRoute $route;
    private Authentication&MockObject $authentication;

    #[\Override]
    protected function setUp(): void
    {
        $this->route = new RefreshTokenRoute(
            $this->authentication = $this->createMock(Authentication::class),
        );
    }

    public function testCreatesActionWithCorrectRouteInfo(): void
    {
        $actual = $this->route;

        $this->assertSame('Refresh API Token', $actual->getName());
        $this->assertSame('/auth/refresh', $actual->getPath());
        $this->assertSame('POST', $actual->getMethod());
        $this->assertSame('Exchanges a valid refresh token for a new token set. This should be used when the access token has expired.', $actual->getDescription());
    }

    public function testUsesComponentsToIssueToken(): void
    {
        $refreshToken = 'foo';
        $expected = ['foo' => 'bar'];

        $tokenSet = $this->createConfiguredMock(TokenSet::class, [
            'toArray' => $expected,
        ]);

        $this->authentication
            ->expects($this->once())
            ->method('refreshToken')
            ->with($this->identicalTo($refreshToken))
            ->willReturn($tokenSet);

        $actual = $this->route->getAction()(['refresh_token' => $refreshToken], null);

        $this->assertSame($expected, $actual);
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('invalidParametersDataProvider')]
    public function testThrowsExceptionInCaseOfInvalidParameters(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Refresh token is missing or empty.');

        $this->route->getAction()($params, null);
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public static function invalidParametersDataProvider(): array
    {
        return [
            'missing refresh_token' => [['foo' => 'bar']],
            'empty refresh_token' => [['refresh_token' => '']],
            'spaces refresh_token' => [['refresh_token' => '   ']],
        ];
    }
}
