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

namespace ILIAS\ApiGateway\Configuration\Infrastructure;

readonly class RandomKeyGenerator
{
    public static function generate(int $length = 64): string
    {
        if ($length <= 0) {
            return '';
        }
        // We need ceil($length / 2) bytes to produce a hex string of at least $length characters.
        // A default length of 64 characters (from 32 bytes of randomness) provides 256 bits of entropy,
        // which is recommended for applications like JWT signing keys (e.g., for HS256).
        $bytesLength = ($length + 1) >> 1;
        /** @psalm-suppress ArgumentTypeCoercion */
        $randomBytes = random_bytes($bytesLength);

        return substr(bin2hex($randomBytes), 0, $length);
    }
}
