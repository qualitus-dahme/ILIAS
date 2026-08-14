<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Model;

use DateTimeImmutable;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenSet;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenSet::class)]
class TokenSetTest extends TestCase
{
    private TokenSet $model;
    private string $accessToken = 'foo';
    private string $refreshToken = 'bar';
    private int $expiresInTimestamp = 1234567890;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = new TokenSet(
            $this->createConfiguredMock(Token::class, [
                'getToken' => $this->accessToken,
                'getExpiresAt' => $this->createConfiguredMock(DateTimeImmutable::class, [
                    'getTimestamp' => $this->expiresInTimestamp,
                ]),
            ]),
            $this->createConfiguredMock(Token::class, [
                'getToken' => $this->refreshToken,
            ]),
        );
    }

    public function testIsSerializableIntoArray(): void
    {
        $expected = [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresInTimestamp,
        ];

        $actual = $this->model->toArray();

        $this->assertSame($expected, $actual);
    }
}
