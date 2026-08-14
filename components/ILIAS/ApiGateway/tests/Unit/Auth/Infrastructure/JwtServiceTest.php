<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure;

use DateTimeImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenPayload;
use ILIAS\ApiGateway\Auth\Infrastructure\JwtService;
use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(JwtService::class)]
class JwtServiceTest extends TestCase
{
    private const string SECRET_KEY = 'super-secret-key-for-testing-should-be-long-for-hs256';
    private const string ALGORITHM = 'HS256';
    private const string ISSUER = 'ILIAS';
    private const int USER_ID = 1337;
    private MockObject|HttpConfigFactory $configFactory;
    private JwtService $service;

    #[\Override]
    protected function setUp(): void
    {
        $this->configFactory = $this->createConfiguredMock(HttpConfigFactory::class, [
            'createAuthConfig' => $this->createConfiguredMock(AuthConfig::class, [
                'getSecretKey' => self::SECRET_KEY,
                'getEncryptionAlgo' => self::ALGORITHM,
                'getIssuer' => self::ISSUER,
            ]),
        ]);

        $this->service = new JwtService($this->configFactory);
    }

    public function testGeneratesValidAccessToken(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 hour');

        $token = $this->service->generate(self::USER_ID, $issuedAt, $expiresIn);

        self::assertInstanceOf(Token::class, $token);
        self::assertSame($expiresIn, $token->getExpiresAt());

        $decodedPayload = (array) JWT::decode($token->getToken(), new Key(self::SECRET_KEY, self::ALGORITHM));
        self::assertSame(self::ISSUER, $decodedPayload['iss']);
        self::assertSame((string) self::USER_ID, $decodedPayload['sub']);
        self::assertSame($issuedAt->getTimestamp(), $decodedPayload['iat']);
        self::assertSame($expiresIn->getTimestamp(), $decodedPayload['exp']);
        self::assertArrayNotHasKey('is_refresh', $decodedPayload);
    }

    public function testGeneratesValidRefreshToken(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 day');

        $token = $this->service->generate(self::USER_ID, $issuedAt, $expiresIn, true);

        self::assertInstanceOf(Token::class, $token);

        $decodedPayload = (array) JWT::decode($token->getToken(), new Key(self::SECRET_KEY, self::ALGORITHM));
        self::assertTrue($decodedPayload['is_refresh']);
    }

    public function testDecodesValidToken(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 hour');
        $tokenString = $this->generateTestToken(['exp' => $expiresIn->getTimestamp()]);

        $payload = $this->service->decode($tokenString);

        self::assertInstanceOf(TokenPayload::class, $payload);
        self::assertSame(self::USER_ID, $payload->getUser()->getId());
        self::assertFalse($payload->isRefresh());
    }

    public function testDecodesValidRefreshToken(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 hour');
        $tokenString = $this->generateTestToken([
            'exp' => $expiresIn->getTimestamp(),
            'is_refresh' => true
        ]);

        $payload = $this->service->decode($tokenString);

        self::assertTrue($payload->isRefresh());
    }

    public function testThrowsExceptionWhenDecodingExpiredToken(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The provided token is invalid or expired.');

        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('-1 hour'); // Expired in the past
        $tokenString = $this->generateTestToken(['exp' => $expiresIn->getTimestamp()]);

        $this->service->decode($tokenString);
    }

    public function testThrowsExceptionWhenDecodingMalformedToken(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The provided token is invalid or expired.');

        $malformedToken = 'this.is.not.a.jwt';

        $this->service->decode($malformedToken);
    }

    public function testThrowsExceptionWhenDecodingTokenWithInvalidSignature(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The provided token is invalid or expired.');

        $key = "wrong-secret-key-long-enough-for-hs256";

        $tokenString = JWT::encode($this->getStandardPayload(), $key, self::ALGORITHM);

        $this->service->decode($tokenString);
    }

    public function testThrowsExceptionWhenDecodingTokenWithInvalidIssuer(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Invalid token issuer.');

        $tokenString = $this->generateTestToken(['iss' => 'invalid-issuer']);

        $this->service->decode($tokenString);
    }

    public function testThrowsExceptionWhenDecodingTokenWithMissingSubject(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Token subject (sub) claim is missing, empty, or not numeric.');

        $payload = $this->getStandardPayload();
        unset($payload['sub']);
        $tokenString = JWT::encode($payload, self::SECRET_KEY, self::ALGORITHM);

        $this->service->decode($tokenString);
    }

    public function testThrowsExceptionWhenDecodingTokenWithNonNumericSubject(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Token subject (sub) claim is missing, empty, or not numeric.');

        $tokenString = $this->generateTestToken(['sub' => 'not-a-number']);

        $this->service->decode($tokenString);
    }

    public function testAccessTokenDoesNotContainJti(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 hour');

        $token = $this->service->generate(self::USER_ID, $issuedAt, $expiresIn);

        $decodedPayload = $this->parsePayloadFromJwt($token->getToken());
        self::assertArrayNotHasKey('jti', $decodedPayload);
    }

    public function testRefreshTokenContainsJti(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 day');

        $token = $this->service->generate(self::USER_ID, $issuedAt, $expiresIn, true);

        $decodedPayload = $this->parsePayloadFromJwt($token->getToken());
        self::assertArrayHasKey('jti', $decodedPayload);
    }

    public function testRefreshTokenJtiIsUniqueAcrossGenerations(): void
    {
        $issuedAt = new DateTimeImmutable();
        $expiresIn = $issuedAt->modify('+1 day');

        $token1 = $this->service->generate(self::USER_ID, $issuedAt, $expiresIn, true);
        $token2 = $this->service->generate(self::USER_ID, $issuedAt, $expiresIn, true);

        $payload1 = $this->parsePayloadFromJwt($token1->getToken());
        $payload2 = $this->parsePayloadFromJwt($token2->getToken());

        self::assertArrayHasKey('jti', $payload1);
        self::assertArrayHasKey('jti', $payload2);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePayloadFromJwt(string $tokenString): array
    {
        return (array) JWT::decode($tokenString, new Key(self::SECRET_KEY, self::ALGORITHM));
    }

    /**
     * @param array<string, string|int|bool> $overridePayload
     */
    private function generateTestToken(array $overridePayload = []): string
    {
        return JWT::encode(
            [...$this->getStandardPayload(), ...$overridePayload],
            self::SECRET_KEY,
            self::ALGORITHM
        );
    }

    /**
     * @return array<string, string|int>
     */
    private function getStandardPayload(): array
    {
        $issuedAt = new DateTimeImmutable();

        return [
            'iss' => self::ISSUER,
            'sub' => (string) self::USER_ID,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $issuedAt->modify('+1 hour')->getTimestamp(),
        ];
    }
}
