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

namespace ILIAS;

use ILIAS\Component\Component;
use ILIAS\Setup\Agent;
use ILIAS\Refinery\Factory;
use ILIAS\Component\Resource\PublicAsset;
use ILIAS\Component\Resource\Endpoint;

class FileDelivery implements Component
{
    public function init(
        array | \ArrayAccess &$define,
        array | \ArrayAccess &$implement,
        array | \ArrayAccess &$use,
        array | \ArrayAccess &$contribute,
        array | \ArrayAccess &$seek,
        array | \ArrayAccess &$provide,
        array | \ArrayAccess &$pull,
        array | \ArrayAccess &$internal,
    ): void {
        $contribute[Agent::class] = static fn(): \ILIAS\FileDelivery\Setup\Agent =>
            new \ILIAS\FileDelivery\Setup\Agent(
                $pull[Factory::class]
            );

        $contribute[PublicAsset::class] = fn(): Endpoint =>
            new Endpoint($this, "deliver.php");
    }
}
