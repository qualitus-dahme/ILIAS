<?php

declare(strict_types=1);

namespace Tests\Unit\Routes\Auth;

use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenSet;
use ILIAS\ApiGateway\Auth\Domain\Repository\UserRepository;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use ILIAS\ApiGateway\Routes\Auth\IssueTokenRoute;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssueTokenRoute::class)]
class IssueTokenRouteTest extends TestCase
{
    private IssueTokenRoute $route;
    private Authentication&MockObject $authentication;
    private UserRepository&MockObject $userRepository;

    #[\Override]
    protected function setUp(): void
    {
        $this->route = new IssueTokenRoute(
            $this->authentication = $this->createMock(Authentication::class),
            $this->userRepository = $this->createMock(UserRepository::class),
        );
    }

    public function testCreatesActionWithCorrectRouteInfo(): void
    {
        $actual = $this->route;

        $this->assertSame('Create API Token', $actual->getName());
        $this->assertSame('/auth/token', $actual->getPath());
        $this->assertSame('POST', $actual->getMethod());
        $this->assertSame('Authenticates a user and returns a new token set (access and refresh tokens).', $actual->getDescription());
    }

    public function testUsesComponentsToIssueToken(): void
    {
        $username = 'foo';
        $password = ' bar ';
        $expected = ['foo' => 'bar'];

        $user = $this->createMock(AuthUser::class);
        $tokenSet = $this->createConfiguredMock(TokenSet::class, [
            'toArray' => $expected,
        ]);

        $this->userRepository
            ->expects($this->once())
            ->method('login')
            ->with($username, $password)
            ->willReturn($user);

        $this->authentication
            ->expects($this->once())
            ->method('createToken')
            ->with($this->identicalTo($user))
            ->willReturn($tokenSet);

        $actual = $this->route->getAction()([
            'username' => $username,
            'password' => $password,
        ], null);

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
        $this->expectExceptionMessage('Username or password is empty.');

        $this->route->getAction()($params, null);
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public static function invalidParametersDataProvider(): array
    {
        return [
            'username is null' => [['username' => null, 'password' => 'bar']],
            'password is null' => [['username' => 'foo', 'password' => null]],
            'missing username' => [['password' => 'bar']],
            'missing password' => [['username' => 'foo']],
            'empty username' => [['username' => '', 'password' => 'bar']],
            'empty password' => [['username' => 'foo', 'password' => '']],
            'spaces username' => [['username' => '   ', 'password' => 'bar']],
        ];
    }

    /**
     * @param array<mixed, mixed> $params
     */
    #[DataProvider('invalidInputTypeDataProvider')]
    public function testThrowsExceptionInCaseOfInvalidInputType(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid input type.');

        $this->route->getAction()($params, null);
    }

    /**
     * @return array<string, array<int, array<mixed, mixed>>>
     */
    public static function invalidInputTypeDataProvider(): array
    {
        return [
            'username is int' => [['username' => 123, 'password' => 'bar']],
            'password is int' => [['username' => 'foo', 'password' => 123]],
            'username is array' => [['username' => ['user'], 'password' => 'bar']],
            'password is array' => [['username' => 'foo', 'password' => ['pass']]],
        ];
    }
}
