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

use DateTimeImmutable;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenPayload;
use ILIAS\ApiGateway\Auth\Domain\Service\TokenProvider;
use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use InvalidArgumentException;
use Override;
use UnexpectedValueException;

final readonly class JwtService implements TokenProvider
{
    public function __construct(
        private HttpConfigFactory $configFactory,
    ) {
    }

    #[Override]
    public function generate(
        int $userId,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresIn,
        bool $isRefresh = false,
    ): Token {
        /**
         * JWT Payload
         *
         * Essential (Practically Mandatory)
         * exp (Expiration Time): Identifies the expiration time on or after which the JWT MUST NOT be accepted for processing.
         * sub (Subject): Identifies the principal that is the subject of the JWT.
         *
         * Recommended
         * iss (Issuer): Identifies the principal that issued the JWT.
         * aud (Audience): Identifies the recipients that the JWT is intended for.
         *
         * Optional
         * nbf (Not Before): Identifies the time before which the JWT MUST NOT be accepted for processing.
         * iat (Issued At): Identifies the time at which the JWT was issued.
         * jti (JWT ID): Provides a unique identifier for the JWT.
         *
         */
        $payload = [
            'iss' => $this->config()->getIssuer(),
            'sub' => (string) $userId,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresIn->getTimestamp(),
        ];

        if ($isRefresh) {
            $payload['is_refresh'] = true;
            // jti ensures refresh tokens are unique even when generated in the same second
            $payload['jti'] = bin2hex(random_bytes(16));
        }

        $token = JWT::encode(
            $payload,
            $this->config()->getSecretKey(),
            $this->config()->getEncryptionAlgo(),
        );

        return new Token($token, $expiresIn);
    }

    #[Override]
    public function decode(string $token): TokenPayload
    {
        try {
            /**
             * @see https://github.com/firebase/php-jwt
             *
             * JWT::decode() throws these exceptions
             *
             * \InvalidArgumentException                    Provided key/key-array was empty or malformed
             * \DomainException                             Provided JWT is malformed
             * \UnexpectedValueException                    Provided JWT was invalid
             * \Firebase\JWT\SignatureInvalidException      Provided JWT was invalid because the signature verification failed
             * \Firebase\JWT\BeforeValidException           Provided JWT is trying to be used before it's eligible as defined by 'nbf'
             * \Firebase\JWT\BeforeValidException           Provided JWT is trying to be used before it's been created as defined by 'iat'
             * \Firebase\JWT\ExpiredException               Provided JWT has since expired, as defined by the 'exp' claim
             *
             */
            $decoded = JWT::decode(
                $token,
                new Key(
                    $this->config()->getSecretKey(),
                    $this->config()->getEncryptionAlgo(),
                ),
            );
        } catch (InvalidArgumentException | DomainException | UnexpectedValueException $e) {
            // catch 'firebase/php-jwt' exceptions in order not to leak third-party exceptions into the application.
            throw new AuthenticationException(
                'The provided token is invalid or expired.',
                $e,
            );
        }

        $payload = (array) $decoded;

        if (!isset($payload['iss']) || $payload['iss'] !== $this->config()->getIssuer()) {
            throw new AuthenticationException('Invalid token issuer.');
        }

        if (!isset($payload['sub']) || !is_numeric($payload['sub'])) {
            throw new AuthenticationException('Token subject (sub) claim is missing, empty, or not numeric.');
        }

        $userId = (int) $payload['sub'];
        $isRefresh = \array_key_exists('is_refresh', $payload) && (bool) $payload['is_refresh'];

        // The JWT::decode function throws an exception if the token is expired.
        // If we reach this point, the token is valid and not expired.

        return new TokenPayload(
            new AuthUser($userId),
            $isRefresh,
        );
    }

    private function config(): AuthConfig
    {
        return $this->configFactory->createAuthConfig();
    }
}
