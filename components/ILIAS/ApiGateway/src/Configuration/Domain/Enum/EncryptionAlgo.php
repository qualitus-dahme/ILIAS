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

namespace ILIAS\ApiGateway\Configuration\Domain\Enum;

enum EncryptionAlgo: string
{
    case HS256 = 'HS256';
    case HS512 = 'HS512';

    public function getKeyMinimumLength(): int
    {
        return match ($this) {
            self::HS256 => 32,
            self::HS512 => 64,
        };
    }
}
