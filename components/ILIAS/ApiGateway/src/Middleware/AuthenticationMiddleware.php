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

namespace ILIAS\ApiGateway\Middleware;

use ILIAS\ApiGateway\Application\Exception\AuthenticationException;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Orchestrates the authentication process by extracting a token from the request
 * and passing it to a dedicated authentication service.
 */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Authentication $authenticationService
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === '' || $authHeader === '0' || !str_starts_with(strtolower($authHeader), 'bearer ')) {
            throw new AuthenticationException('Authorization header missing or invalid.');
        }

        $token = trim(substr($authHeader, 7)); // Length of "Bearer "

        if ($token === '') {
            throw new AuthenticationException('Authorization token cannot be empty.');
        }

        $authenticatedUser = $this->authenticationService->validateToken($token);

        // Add the fully populated user object to the request attributes for downstream use.
        $request = $request->withAttribute('authenticated_user', $authenticatedUser);

        return $handler->handle($request);
    }
}
