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

namespace ILIAS\ApiGateway\Application;

use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Webservice\Domain\Model\Payload;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function array_merge;

/**
 * This class is responsible for the entire lifecycle of a successfully matched route.
 */
readonly class ResponseHandler
{
    private const string ATTR_KEY_AUTH_USER = 'authenticated_user';

    public function __construct(
        private Webservice $service,
    ) {}

    /**
     * @param array<mixed, mixed> $args
     */
    public function __invoke(
        Request $request,
        Response $response,
        array $args,
        Action $action,
    ): Response {
        /** @var AuthUser|null */
        $authUser = $request->getAttribute(self::ATTR_KEY_AUTH_USER);
        $request = $request->withoutAttribute(self::ATTR_KEY_AUTH_USER);

        $params = [
            ...$request->getQueryParams(),
            ...(array) $request->getParsedBody(),
        ];

        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $bodyContents = $body->getContents();
            if ($bodyContents !== '' && $bodyContents !== '0') {
                $bodyParams = json_decode($bodyContents, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($bodyParams)) {
                    $params = array_merge($params, $bodyParams);
                }
            }
        }

        $responseBody = $action(
            [...$params, ...$args],
            $authUser,
        );

        $successPayload = new Payload($responseBody);
        $responsePayload = $this->service->handle($successPayload);

        foreach ($responsePayload->getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write($responsePayload->getBody());

        return $response;
    }
}
