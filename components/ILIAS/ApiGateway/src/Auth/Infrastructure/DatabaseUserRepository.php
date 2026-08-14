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

use ilAuthFrontendCredentials;
use ilAuthProviderFactory;
use ilAuthStatus;
use ilAuthUtils;
use ilDBConstants;
use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Auth\Domain\Repository\UserRepository;
use ILIAS\ApiGateway\GlobalDICAccessTrait;
use ILIAS\HTTP\StatusCode;
use InvalidArgumentException;
use Override;

/**
 * @codeCoverageIgnore To be tested when database is loaded by DI not global
 */
final readonly class DatabaseUserRepository implements UserRepository
{
    use GlobalDICAccessTrait;

    #[Override]
    public function getById(int $userId): AuthUser
    {
        $database = $this->getDatabase();

        $userQuery = $database->queryF(
            'SELECT usr_id FROM usr_data WHERE usr_id = %s',
            [ilDBConstants::T_INTEGER],
            [$userId],
        );

        $result = $userQuery->fetchAssoc();

        if (null === $result || empty($result) || !isset($result['usr_id']) || $result['usr_id'] !== $userId) {
            throw new InvalidArgumentException('User not found', StatusCode::HTTP_NOT_FOUND);
        }

        return new AuthUser(
            $result['usr_id'],
        );
    }

    #[Override]
    public function login(string $username, string $password): AuthUser
    {
        $credentials = new ilAuthFrontendCredentials();
        $credentials->setUsername($username);
        $credentials->setPassword($password);

        $providerFactory = new ilAuthProviderFactory();
        $provider = $providerFactory->getProviderByAuthMode($credentials, ilAuthUtils::AUTH_LOCAL);

        $status = ilAuthStatus::getInstance();
        $authenticated = $provider?->doAuthentication($status);

        if (!$authenticated) {
            throw new AuthenticationException('Wrong username or password.');
        }

        return new AuthUser(
            $status->getAuthenticatedUserId(),
        );
    }
}
