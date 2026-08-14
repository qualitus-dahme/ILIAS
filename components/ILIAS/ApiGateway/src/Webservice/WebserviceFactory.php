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

namespace ILIAS\ApiGateway\Webservice;

use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\ApiGateway\Webservice\Domain\Webservice;
use ILIAS\ApiGateway\Webservice\Infrastructure\RestWebservice;
use InvalidArgumentException;

readonly class WebserviceFactory
{
    public function create(WebConfig $config): Webservice
    {
        $protocol = $config->getProtocol();

        return match ($protocol) {
            ServiceProtocol::REST => new RestWebservice($config),
            /**
             * As a defensive mechanism for truly unhandled cases, so testing would be hard as
             * this should never be hit. In production ALL ServiceProtocol enum cases are
             * translated into existing webservices. Therefore, it is ignored from code coverage.
             */
            // @codeCoverageIgnoreStart
            default => throw new InvalidArgumentException(
                \sprintf("Unsupported service protocol: %s", $protocol->name)
            ),
            // @codeCoverageIgnoreEnd
        };
    }
}
