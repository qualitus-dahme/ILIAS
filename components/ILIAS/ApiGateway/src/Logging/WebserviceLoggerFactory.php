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

namespace ILIAS\ApiGateway\Logging;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Psr\Log\LoggerInterface;

readonly class WebserviceLoggerFactory
{
    /**
     * @todo: replace this with final logger
     */
    public function create(string $name): LoggerInterface
    {
        $logger = new Logger($name);

        $logger->pushHandler(new StreamHandler(dirname(__DIR__, 5) . "/ws_$name.log", Level::Debug));
        $logger->pushHandler(new FirePHPHandler());

        return $logger;
    }
}
