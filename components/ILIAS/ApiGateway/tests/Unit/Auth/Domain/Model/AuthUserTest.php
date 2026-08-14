<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Model;

use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthUser::class)]
class AuthUserTest extends TestCase
{
    private AuthUser $model;
    private int $id;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = new AuthUser(
            $this->id = 123,
        );
    }

    public function testHasAccessorToId(): void
    {
        $this->assertSame(
            $this->id,
            $this->model->getId(),
        );
    }

    #[RunInSeparateProcess]
    public function testCreatesAnonymousUser(): void
    {
        define('ANONYMOUS_USER_ID', $anonymousUserId = 123);

        $this->assertEquals(
            new AuthUser($anonymousUserId),
            AuthUser::anonymous(),
        );
    }

    public function testCreatesAnonymousUserWithDefaultId(): void
    {
        $this->assertEquals(
            new AuthUser(0),
            AuthUser::anonymous(),
        );
    }
}
