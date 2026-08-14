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

namespace ILIAS\ApiGateway\Auth\Domain\Service;

use DateTimeImmutable;
use ILIAS\ApiGateway\Auth\Domain\Model\TokenPayload;
use ILIAS\ApiGateway\Auth\Domain\Model\Token;

/**
 * Defines the contract for a service that can generate and validate authentication tokens.
 * This abstracts the underlying token technology (e.g., static, JWT, OAuth2).
 */
interface TokenProvider
{
    /**
     * Generates a new authentication token from a given user id.
     */
    public function generate(
        int $userId,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresIn,
        bool $isRefresh = false,
    ): Token;

    /**
     * Validates a token string and returns the data it contains.
     */
    public function decode(string $token): TokenPayload;
}
