<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure;

use DateTimeImmutable;
use DomainException;
use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Model\RefreshToken;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenPayload;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenSet;
use ILIAS\ApiGateway\Auth\Domain\Repository\RefreshTokenRepository;
use ILIAS\ApiGateway\Auth\Domain\Repository\UserRepository;
use ILIAS\ApiGateway\Auth\Domain\Service\TokenProvider;
use ILIAS\ApiGateway\Auth\Infrastructure\AuthService;
use ILIAS\ApiGateway\Configuration\Domain\Enum\EncryptionAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AuthService::class)]
class AuthServiceTest extends TestCase
{
    private const int USER_ID = 1337;
    private const int ACCESS_TOKEN_EXPIRY = 3600; // 1 hour
    private const int REFRESH_TOKEN_EXPIRY = 86400; // 1 day
    private const string ENCRYPTION_ALGO = EncryptionAlgo::HS256->value;
    private const string HASH_ALGO = 'sha256';

    private MockObject&TokenProvider $tokenProvider;
    private MockObject&UserRepository $userRepository;
    private MockObject&RefreshTokenRepository $refreshTokenRepository;
    private MockObject&HttpConfigFactory $configFactory;
    private AuthService $service;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenProvider = $this->createMock(TokenProvider::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $this->configFactory = $this->createConfiguredMock(HttpConfigFactory::class, [
            'createAuthConfig' => $this->createConfiguredMock(AuthConfig::class, [
                'getSecretKey' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
                'getAccessTokenExpiry' => self::ACCESS_TOKEN_EXPIRY,
                'getRefreshTokenExpiry' => self::REFRESH_TOKEN_EXPIRY,
                'getEncryptionAlgo' => self::ENCRYPTION_ALGO,
                'getHashAlgo' => self::HASH_ALGO,
            ]),
        ]);

        $this->service = new AuthService(
            $this->tokenProvider,
            $this->userRepository,
            $this->refreshTokenRepository,
            $this->configFactory,
        );
    }

    public function testThrowsExceptionIfSecretKeyLengthIsInvalid(): void
    {
        self::expectException(RuntimeException::class);

        $configFactory = $this->createConfiguredMock(HttpConfigFactory::class, [
            'createAuthConfig' => $this->createConfiguredMock(AuthConfig::class, [
                'getSecretKey' => 'short_key',
                'getAccessTokenExpiry' => self::ACCESS_TOKEN_EXPIRY,
                'getRefreshTokenExpiry' => self::REFRESH_TOKEN_EXPIRY,
                'getEncryptionAlgo' => self::ENCRYPTION_ALGO,
                'getHashAlgo' => self::HASH_ALGO,
            ]),
        ]);

        $this->service = new AuthService(
            $this->tokenProvider,
            $this->userRepository,
            $this->refreshTokenRepository,
            $configFactory,
        );

        $user = $this->createConfiguredMock(AuthUser::class, [
            'getId' => self::USER_ID,
        ]);

        $this->service->createToken($user);
    }

    public function testCreateTokenSuccessfully(): void
    {
        $user = new AuthUser(self::USER_ID);
        $accessToken = new Token('access_token_string', new DateTimeImmutable());
        $refreshToken = new Token('refresh_token_string', new DateTimeImmutable());

        $this->tokenProvider->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(
                fn(int $userId, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresIn, bool $isRefresh = false) => $isRefresh ? $refreshToken : $accessToken
            );

        $this->refreshTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(RefreshToken::class));

        $tokenSet = $this->service->createToken($user);

        self::assertInstanceOf(TokenSet::class, $tokenSet);

        $tokenSetArray = $tokenSet->toArray();

        self::assertSame($accessToken->getToken(), $tokenSetArray['access_token']);
        self::assertSame($refreshToken->getToken(), $tokenSetArray['refresh_token']);
        self::assertSame($accessToken->getExpiresAt()->getTimestamp(), $tokenSetArray['expires_at']);
    }

    public function testRefreshTokenSuccessfully(): void
    {
        $user = new AuthUser(self::USER_ID);
        $oldRefreshTokenString = 'old_refresh_token';
        $payload = new TokenPayload($user, true);

        $this->tokenProvider->expects($this->once())
            ->method('decode')
            ->with($oldRefreshTokenString)
            ->willReturn($payload);

        $storedToken = $this->createConfiguredMock(RefreshToken::class, [
            'isRevoked' => false,
            'isExpired' => false,
        ]);
        $this->refreshTokenRepository->expects($this->once())
            ->method('findByHash')
            ->with(hash(self::HASH_ALGO, $oldRefreshTokenString))
            ->willReturn($storedToken);

        $storedToken->expects($this->once())
            ->method('revoke');

        $this->refreshTokenRepository->expects($this->exactly(2)) // Once for the revoke, once for the save())
            ->method('save');

        $newAccessToken = new Token('new_access_token', new DateTimeImmutable());
        $newRefreshToken = new Token('new_refresh_token', new DateTimeImmutable());
        $this->tokenProvider->method('generate')->willReturnOnConsecutiveCalls($newAccessToken, $newRefreshToken);

        $newTokenSet = $this->service->refreshToken($oldRefreshTokenString);

        $newTokenSetArray = $newTokenSet->toArray();

        self::assertSame($newAccessToken->getToken(), $newTokenSetArray['access_token']);
        self::assertSame($newRefreshToken->getToken(), $newTokenSetArray['refresh_token']);
        self::assertSame($newAccessToken->getExpiresAt()->getTimestamp(), $newTokenSetArray['expires_at']);
    }

    public function testRefreshTokenThrowsExceptionForNonRefreshToken(): void
    {
        self::expectException(DomainException::class);
        self::expectExceptionMessage('Token is not a refresh token.');

        $payload = new TokenPayload(new AuthUser(self::USER_ID), false); // Not a refresh token

        $this->tokenProvider->method('decode')->willReturn($payload);

        $this->service->refreshToken('any_token');
    }

    public function testRefreshTokenThrowsExceptionForInvalidToken(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Refresh token is invalid or has been revoked.');

        $payload = new TokenPayload(new AuthUser(self::USER_ID), true);

        $this->tokenProvider->method('decode')->willReturn($payload);
        $this->refreshTokenRepository->method('findByHash')->willReturn(null); // Not found in DB

        $this->service->refreshToken('any_token');
    }

    public function testRefreshTokenThrowsExceptionForRevokedToken(): void
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Refresh token is invalid or has been revoked.');

        $payload = new TokenPayload(new AuthUser(self::USER_ID), true);

        $this->tokenProvider->method('decode')->willReturn($payload);

        $revokedToken = $this->createConfiguredMock(RefreshToken::class, ['isRevoked' => true]);

        $this->refreshTokenRepository->method('findByHash')->willReturn($revokedToken);

        $this->service->refreshToken('any_token');
    }

    public function testValidateTokenSuccessfully(): void
    {
        $user = new AuthUser(self::USER_ID);
        $tokenString = 'valid_token_string';
        $payload = new TokenPayload($user, false);

        $this->tokenProvider->expects($this->once())
            ->method('decode')
            ->with($tokenString)
            ->willReturn($payload);
        $this->userRepository->expects($this->once())
            ->method('getById')
            ->with(self::USER_ID)
            ->willReturn($user);

        $resultUser = $this->service->validateToken($tokenString);

        self::assertSame($user, $resultUser);
    }

    public function testValidateTokenThrowsExceptionOnProviderError(): void
    {
        self::expectException(AuthenticationException::class);

        $tokenString = 'invalid_token_string';

        $this->tokenProvider->method('decode')
            ->with($tokenString)
            ->willThrowException(new AuthenticationException('Provider error'));

        $this->service->validateToken($tokenString);
    }
}
