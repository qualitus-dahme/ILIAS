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

namespace ILIAS\ApiGateway;

use ilDBInterface;
use ILIAS\DI\Container;
use RuntimeException;
use ilLogger;

/**
 * This is a workaround to work with legacy dependency.
 * When this is used, it is a temporary adapter
 */
trait GlobalDICAccessTrait
{
    private function dic(): ?Container
    {
        global $DIC;

        if (!isset($DIC) || !$DIC instanceof Container) {
            return null;
        }

        return $DIC;
    }

    protected function database(): ?ilDBInterface
    {
        $database = $this->dic()?->database();

        if ($database && !$database instanceof ilDBInterface) {
            return null;
        }

        return $database;
    }

    protected function getDatabase(): ilDBInterface
    {
        return $this->database() ?? throw new RuntimeException('No database connection');
    }

    protected function logger(?string $name = null): ?ilLogger
    {
        $logging = $this->dic()?->logger();

        if ($logging === null) {
            return null;
        }

        if ($name === null) {
            return $logging->root();
        }

        $logger = $logging->$name();

        if ($logger !== null && $logger instanceof ilLogger) {
            return $logger;
        }

        return null;
    }
}
