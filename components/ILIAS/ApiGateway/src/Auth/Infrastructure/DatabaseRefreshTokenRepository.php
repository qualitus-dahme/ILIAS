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
use ilDBConstants;
use ilDBInterface;
use ILIAS\ApiGateway\Auth\Domain\Model\RefreshToken;
use ILIAS\ApiGateway\Auth\Domain\Repository\RefreshTokenRepository;
use ILIAS\ApiGateway\GlobalDICAccessTrait;
use Override;

/**
 * @codeCoverageIgnore To be tested when database is loaded by DI not global
 */
final readonly class DatabaseRefreshTokenRepository implements RefreshTokenRepository
{
    use GlobalDICAccessTrait;

    private const string TABLE_NAME = 'apig_refresh_tokens';
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    #[Override]
    public function save(RefreshToken $token): void
    {
        $database = $this->database();

        if (!$database instanceof ilDBInterface) {
            return;
        }

        $tokenId = $token->getId();

        $now = new DateTimeImmutable();

        if ($tokenId !== null) {
            // Update existing token (e.g., for revocation)
            $database->update(
                self::TABLE_NAME,
                [
                    'user_id' => [ilDBConstants::T_INTEGER, $token->getUserId()],
                    'token_hash' => [ilDBConstants::T_TEXT, $token->getTokenHash()],
                    'is_revoked' => [ilDBConstants::T_INTEGER, (int) $token->isRevoked()],
                    'expires_at' => [ilDBConstants::T_TIMESTAMP, $token->getExpiresAt()->format(self::TIMESTAMP_FORMAT)],
                    'updated_at' => [ilDBConstants::T_TIMESTAMP, $now->format(self::TIMESTAMP_FORMAT)],
                ],
                [
                    'id' => [ilDBConstants::T_INTEGER, $tokenId],
                ],
            );
        } else {
            // Insert new token
            $rowValues = [
                'id' => [ilDBConstants::T_INTEGER, $database->nextId(self::TABLE_NAME)],
                'user_id' => [ilDBConstants::T_INTEGER, $token->getUserId()],
                'token_hash' => [ilDBConstants::T_TEXT, $token->getTokenHash()],
                'is_revoked' => [ilDBConstants::T_INTEGER, (int) $token->isRevoked()],
                'expires_at' => [ilDBConstants::T_TIMESTAMP, $token->getExpiresAt()->format(self::TIMESTAMP_FORMAT)],
                'created_at' => [ilDBConstants::T_TIMESTAMP, $now->format(self::TIMESTAMP_FORMAT)],
                'updated_at' => [ilDBConstants::T_TIMESTAMP, $now->format(self::TIMESTAMP_FORMAT)],
            ];
            $database->insert(
                self::TABLE_NAME,
                $rowValues,
            );
        }
    }

    #[Override]
    public function findByHash(string $tokenHash): ?RefreshToken
    {
        $database = $this->database();

        if (!$database instanceof ilDBInterface || $tokenHash === '') {
            return null;
        }

        $result = $database->queryF(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE token_hash = %s',
            [ilDBConstants::T_TEXT],
            [$tokenHash]
        );

        if ($result->numRows() === 0) {
            return null;
        }

        $row = $result->fetchAssoc();

        if (null === $row) {
            return null;
        }

        $tokenHash = $row['token_hash'];

        return new RefreshToken(
            (int) $row['user_id'],
            $tokenHash,
            new DateTimeImmutable($row['expires_at']),
            (int) $row['id'],
            (bool) $row['is_revoked'],
        );
    }
}
