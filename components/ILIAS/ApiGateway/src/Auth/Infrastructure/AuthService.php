<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\ApiGateway\Auth\Infrastructure;

use DomainException;
use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Model\RefreshToken;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenSet;
use ILIAS\ApiGateway\Auth\Domain\Repository\RefreshTokenRepository;
use ILIAS\ApiGateway\Auth\Domain\Repository\UserRepository;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use ILIAS\ApiGateway\Auth\Domain\Service\TokenProvider;
use ILIAS\ApiGateway\Configuration\Domain\Enum\EncryptionAlgo;
use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use Override;
use RuntimeException;

final class AuthService implements Authentication
{
    private ?AuthConfig $config = null;

    public function __construct(
        private readonly TokenProvider $tokenProvider,
        private readonly UserRepository $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly HttpConfigFactory $configFactory,
    ) {
    }

    #[Override]
    public function createToken(AuthUser $user): TokenSet
    {
        $userId = $user->getId();
        $issuedAt = new \DateTimeImmutable();
        $accessTokenExpiry = $issuedAt->modify('+' . $this->config()->getAccessTokenExpiry() . ' seconds');
        $refreshTokenExpiry = $issuedAt->modify('+' . $this->config()->getRefreshTokenExpiry() . ' seconds');

        $accessToken = $this->tokenProvider->generate(
            $userId,
            $issuedAt,
            $accessTokenExpiry,
        );
        $refreshToken = $this->tokenProvider->generate(
            $userId,
            $issuedAt,
            $refreshTokenExpiry,
            true,
        );

        $this->saveRefreshToken($user, $refreshToken);

        return new TokenSet($accessToken, $refreshToken);
    }

    #[Override]
    public function refreshToken(string $refreshToken): TokenSet
    {
        $payload = $this->tokenProvider->decode($refreshToken);

        if (false === $payload->isRefresh()) {
            throw new DomainException('Token is not a refresh token.');
        }

        $tokenHash = $this->hashToken($refreshToken);
        $storedToken = $this->refreshTokenRepository->findByHash($tokenHash);

        if ($storedToken === null || $storedToken->isRevoked() || $storedToken->isExpired()) {
            throw new AuthenticationException('Refresh token is invalid or has been revoked.');
        }

        // revoke the old token after use (rotation)
        $this->revokeRefreshToken($storedToken);

        $user = $payload->getUser();

        return $this->createToken($user);
    }

    #[Override]
    public function validateToken(string $token): AuthUser
    {
        $payload = $this->tokenProvider->decode($token);
        $payloadUser = $payload->getUser();

        return $this->userRepository->getById(
            $payloadUser->getId()
        );
    }

    private function saveRefreshToken(AuthUser $user, Token $token): void
    {
        $hash = $this->hashToken($token->getToken());

        $refreshToken = new RefreshToken(
            $user->getId(),
            $hash,
            $token->getExpiresAt(),
        );

        $this->refreshTokenRepository->save($refreshToken);
    }

    private function revokeRefreshToken(RefreshToken $token): void
    {
        $token->revoke();

        $this->refreshTokenRepository->save($token);
    }

    private function hashToken(string $token): string
    {
        return hash($this->config()->getHashAlgo(), $token);
    }

    private function config(): AuthConfig
    {
        if ($this->config === null) {
            /**
             * Validation moved from ILIAS/ApiGateway/Application/Factory/HttpConfigFactory
             * to prevent exceptions on initialization @ ApiGateway.php
             */
            $config = $this->configFactory->createAuthConfig();

            $secretKey = $config->getSecretKey();
            $encryptionAlgo = $config->getEncryptionAlgo();

            $keyLength = \strlen($secretKey);
            $minLength = EncryptionAlgo::from($encryptionAlgo)->getKeyMinimumLength();

            if ($minLength > 0 && $keyLength < $minLength) {
                throw new RuntimeException(
                    "Invalid secret key length. Minimum required is {$minLength} characters, but key is {$keyLength} characters long."
                );
            }

            $this->config = $config;
        }

        return $this->config;
    }
}
