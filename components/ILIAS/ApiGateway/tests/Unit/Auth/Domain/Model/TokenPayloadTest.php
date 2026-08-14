<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Model;

use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenPayload::class)]
class TokenPayloadTest extends TestCase
{
    private TokenPayload $model;
    private AuthUser&MockObject $user;
    private bool $isRefresh;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = new TokenPayload(
            $this->user = $this->createMock(AuthUser::class),
            $this->isRefresh = false,
        );
    }

    public function testHasAccessorToUser(): void
    {
        $this->assertSame(
            $this->user,
            $this->model->getUser(),
        );
    }

    public function testHasAccessorToIsRefresh(): void
    {
        $this->assertSame(
            $this->isRefresh,
            $this->model->isRefresh(),
        );
    }
}
