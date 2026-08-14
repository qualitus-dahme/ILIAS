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

namespace ILIAS\ScormAicc\Services;

use Throwable;

class ScormAccessChecker
{
    public function checkAccessOfUser(int $usr_id, string $permission, int $ref_id): bool
    {
        global $ilAccess;

        if (!is_object($ilAccess) || !method_exists($ilAccess, 'checkAccessOfUser')) {
            return false;
        }

        try {
            return $ilAccess->checkAccessOfUser(
                $usr_id,
                $permission,
                '',
                $ref_id
            );
        } catch (Throwable) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        global $ilAccess;

        if (is_object($ilAccess) && method_exists($ilAccess, 'checkAccessOfUser')) {
            return '$ilAccess';
        }

        return 'none';
    }
}
