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

namespace ILIAS\ApiGateway\Routing;

use InvalidArgumentException;
use ValueError;

enum HttpMethod: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';

    public static function fromAny(mixed $method): self
    {
        if (!\is_scalar($method) && (!\is_object($method) || !method_exists($method, '__toString'))) {
            throw new InvalidArgumentException("Invalid HTTP method type provided. Must be scalar or an object with __toString().");
        }

        try {
            $method = (string) $method;
            $method = strtoupper($method);

            return self::from($method);
        } catch (ValueError $e) {
            throw new InvalidArgumentException("Invalid HTTP method: $method", 0, $e);
        }
    }
}
