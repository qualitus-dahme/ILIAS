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

namespace ILIAS\ApiGateway\Configuration\Domain\Model;

readonly class AuthConfig
{
    public function __construct(
        private string $issuer,
        private string $secretKey,
        private string $encryptionAlgo,
        private string $hashAlgo,
        private int $accessTokenExpiry,
        private int $refreshTokenExpiry,
    ) {
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getEncryptionAlgo(): string
    {
        return $this->encryptionAlgo;
    }

    public function getHashAlgo(): string
    {
        return $this->hashAlgo;
    }

    public function getAccessTokenExpiry(): int
    {
        return $this->accessTokenExpiry;
    }

    public function getRefreshTokenExpiry(): int
    {
        return $this->refreshTokenExpiry;
    }
}
