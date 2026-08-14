<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use Closure;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Routes\ApiRoute;
use ILIAS\ApiGateway\Routing\Action;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiRouteTest extends TestCase
{
    private ApiRoute $apiRoute;
    private string $name = 'foo';
    private string $path = '/test';
    private string $method = 'testMethod';
    private string $description = 'A test API action';
    /** @var array<string> */
    private array $middlewares = ['Middleware1', 'Middleware2'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->apiRoute = new ApiRoute(
            $this->name,
            $this->path,
            $this->method,
            $this->description,
            fn(): string => 'handled',
            $this->middlewares,
        );
    }

    public function testHasAccessorToName(): void
    {
        self::assertSame(
            $this->name,
            $this->apiRoute->getName(),
        );
    }

    public function testHasAccessorToPath(): void
    {
        self::assertSame(
            $this->path,
            $this->apiRoute->getPath(),
        );
    }

    public function testHasAccessorToMethod(): void
    {
        self::assertSame(
            $this->method,
            $this->apiRoute->getMethod(),
        );
    }

    public function testHasAccessorToDescription(): void
    {
        self::assertSame(
            $this->description,
            $this->apiRoute->getDescription(),
        );
    }

    public function testHasAccessorToMiddlewares(): void
    {
        self::assertSame(
            $this->middlewares,
            $this->apiRoute->getMiddlewares(),
        );
    }

    public function testCreatesInstanceWithoutOptionalParameters(): void
    {
        $actual = new ApiRoute(
            $this->name,
            $this->path,
            $this->method,
            $this->description,
            fn(): string => 'handled',
        );

        self::assertEmpty(
            $actual->getMiddlewares()
        );
    }

    #[DataProvider('edgeCasesDataProvider')]
    public function testHandlesEdgeCases(
        string $name,
        string $path,
        string $method,
        string $description,
    ): void {
        // A simple action for instantiation, as its execution isn't the focus here
        $action = fn(): null => null;

        $actual = new ApiRoute(
            $name,
            $path,
            $method,
            $description,
            $action,
        );

        self::assertSame(
            $name,
            $actual->getName(),
        );

        self::assertSame(
            $path,
            $actual->getPath(),
        );

        self::assertSame(
            $method,
            $actual->getMethod(),
        );

        self::assertSame(
            $description,
            $actual->getDescription(),
        );
    }

    /**
     * @return array<string, array{name: string, path: string, method: string, description: string}>
     */
    public static function edgeCasesDataProvider(): array
    {
        return [
            'empty_name_and_description' => [
                'name' => '', // CASE TESTED
                'path' => '/empty-name',
                'method' => 'GET',
                'description' => '', // CASE TESTED
            ],
            'empty_path' => [
                'name' => 'Root Path',
                'path' => '', // CASE TESTED
                'method' => 'GET',
                'description' => 'Route for the root path.',
            ],
            // 'multiple_methods' => [
            //     'name' => 'Multi Method',
            //     'path' => '/multi',
            //     'methods' => ['GET', 'POST', 'PUT'], // CASE TESTED
            //     'description' => 'Route supporting multiple HTTP methods.',
            // ],
            'single_method' => [
                'name' => 'Single Method',
                'path' => '/single',
                'method' => 'PATCH', // CASE TESTED
                'description' => 'Route with a single HTTP method.',
            ],
        ];
    }

    /**
     * @param array<string, int|string|bool> $params
     */
    #[DataProvider('invokableActionDataProvider')]
    public function testProvidesInvokableAction(
        Closure $action,
        mixed $expected,
        array $params,
        ?AuthUser $user,
    ): void {
        $apiRoute = new ApiRoute(
            $this->name,
            $this->path,
            $this->method,
            $this->description,
            $action,
        );

        $routeAction = $apiRoute->getAction();

        self::assertInstanceOf(Action::class, $routeAction);

        $actual = $routeAction($params, $user);

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{action: Closure, expected: mixed, params: array<string, int|string|bool>, user: ?AuthUser}>
     */
    public static function invokableActionDataProvider(): array
    {
        return [
            'action_returns_string_with_params' => [
                'action' => fn(array $params, ?AuthUser $_user) => 'Result: ' . $params['id'],
                'expected' => 'Result: 42',
                'params' => ['id' => '42'],
                'user' => null,
            ],
            'action_returns_integer_no_params' => [
                'action' => fn(array $_params, ?AuthUser $_user) => 123,
                'expected' => 123,
                'params' => [],
                'user' => null,
            ],
            'action_returns_array_with_params' => [
                'action' => fn(array $params, ?AuthUser $_user) => ['status' => 'ok', 'data' => $params],
                'expected' => ['status' => 'ok', 'data' => ['item' => 'new']],
                'params' => ['item' => 'new'],
                'user' => null,
            ],
            'action_returns_boolean' => [
                'action' => fn(array $_params, ?AuthUser $_user) => true,
                'expected' => true,
                'params' => [],
                'user' => null,
            ],
            'action_with_no_params_expected' => [
                'action' => fn(array $_params, ?AuthUser $_user) => 'OK',
                'expected' => 'OK',
                'params' => [],
                'user' => null,
            ],
            'action_uses_user_id' => [
                'action' => fn(array $params, ?AuthUser $user) => $user ? 'User ID: ' . $user->getId() : 'No user',
                'expected' => 'User ID: 99',
                'params' => [],
                'user' => new AuthUser(99),
            ],
            'action_no_user_provided' => [
                'action' => fn(array $params, ?AuthUser $user) => $user ? 'User ID: ' . $user->getId() : 'No user',
                'expected' => 'No user',
                'params' => [],
                'user' => null,
            ],
        ];
    }
}
