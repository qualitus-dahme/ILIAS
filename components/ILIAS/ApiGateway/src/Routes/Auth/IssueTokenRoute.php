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

namespace ILIAS\ApiGateway\Routes\Auth;

use ILIAS\ApiGateway\Auth\Domain\Repository\UserRepository;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use ILIAS\ApiGateway\Routes\ApiRoute;
use ILIAS\HTTP\StatusCode;
use InvalidArgumentException;

use function is_string;

readonly class IssueTokenRoute extends ApiRoute
{
    public function __construct(
        private Authentication $authentication,
        private UserRepository $userRepository,
    ) {
        parent::__construct(
            'Create API Token',
            '/auth/token',
            'POST',
            'Authenticates a user and returns a new token set (access and refresh tokens).',
            function (array $params): array {
                $username = $params['username'] ?? '';
                $password = $params['password'] ?? '';

                if (!is_string($username) || !is_string($password)) {
                    throw new InvalidArgumentException('Invalid input type.', StatusCode::HTTP_BAD_REQUEST);
                }

                $username = trim($username);

                if (\in_array('', [$username, $password])) {
                    throw new InvalidArgumentException('Username or password is empty.', StatusCode::HTTP_BAD_REQUEST);
                }

                $user = $this->userRepository->login($username, $password);

                return $this->authentication->createToken($user)->toArray();
            },
        );
    }
}
