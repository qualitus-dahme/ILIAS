<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Model;

use DateInterval;
use DateTimeImmutable;
use ILIAS\ApiGateway\Auth\Domain\Model\RefreshToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshToken::class)]
class RefreshTokenTest extends TestCase
{
    private RefreshToken $model;
    private int $userId;
    private string $tokenHash;
    private DateTimeImmutable $expiresAt;
    private int $refreshTokenId;
    private bool $isRevoked;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = new RefreshToken(
            $this->userId = 123,
            $this->tokenHash = 'foo-bar',
            $this->expiresAt = new DateTimeImmutable(),
            $this->refreshTokenId = 321,
            $this->isRevoked = false,
        );
    }

    public function testHasAccessorToId(): void
    {
        $this->assertSame(
            $this->refreshTokenId,
            $this->model->getId(),
        );
    }

    public function testHasAccessorToUserId(): void
    {
        $this->assertSame(
            $this->userId,
            $this->model->getUserId(),
        );
    }

    public function testHasAccessorToTokenHash(): void
    {
        $this->assertSame(
            $this->tokenHash,
            $this->model->getTokenHash(),
        );
    }

    public function testHasAccessorToExpiresAt(): void
    {
        $this->assertSame(
            $this->expiresAt,
            $this->model->getExpiresAt(),
        );
    }

    public function testHasAccessorToIsRevoked(): void
    {
        $this->assertSame(
            $this->isRevoked,
            $this->model->isRevoked(),
        );
    }

    public function testRevokesToken(): void
    {
        $this->assertFalse(
            $this->model->isRevoked()
        );

        $this->model->revoke();

        $this->assertTrue(
            $this->model->isRevoked()
        );
    }

    public function testReturnsIsExpired(): void
    {
        $now = new DateTimeImmutable();
        $expiresAtPast = $now->sub(new DateInterval('PT1H'));
        $expiresAtFuture = $now->add(new DateInterval('PT1H'));

        $actualPast = new RefreshToken(
            $this->userId,
            $this->tokenHash,
            $expiresAtPast,
            $this->refreshTokenId,
            $this->isRevoked,
        );

        $actualFuture = new RefreshToken(
            $this->userId,
            $this->tokenHash,
            $expiresAtFuture,
            $this->refreshTokenId,
            $this->isRevoked,
        );

        $this->assertTrue($actualPast->isExpired());
        $this->assertFalse($actualFuture->isExpired());
    }
}
