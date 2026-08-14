<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Model;

use DateTimeImmutable;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Token::class)]
class TokenTest extends TestCase
{
    private Token $model;
    private string $token;
    private DateTimeImmutable&MockObject $expiresAt;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = new Token(
            $this->token = 'foo',
            $this->expiresAt = $this->createMock(DateTimeImmutable::class),
        );
    }

    public function testHasAccessorToGetToken(): void
    {
        $this->assertSame(
            $this->token,
            $this->model->getToken(),
        );
    }

    public function testHasAccessorToGetExpiresAt(): void
    {
        $this->assertSame(
            $this->expiresAt,
            $this->model->getExpiresAt(),
        );
    }
}
