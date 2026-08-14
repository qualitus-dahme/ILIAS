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

namespace ILIAS\ApiGateway\Application\Exception;

use Exception;
use ILIAS\HTTP\StatusCode;
use Throwable;

/**
 * Base exception for authentication failures (HTTP 401 Unauthorized).
 *
 * This exception and its subclasses are used to indicate issues with client authentication,
 * such as invalid credentials, missing tokens, or expired tokens.
 */
class AuthenticationException extends Exception
{
    public function __construct(
        string $message = "Authentication failed.",
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, StatusCode::HTTP_UNAUTHORIZED, $previous);
    }
}
