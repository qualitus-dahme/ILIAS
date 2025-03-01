<?php

declare(strict_types=1);

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

namespace ILIAS\Repository\LastVisited;

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class NavigationSessionRepository
{
    public const KEY = "il_nav_history";

    public function __construct()
    {
    }

    public function setHistory(array $hist): void
    {
        \ilSession::set(self::KEY, serialize($hist));
    }

    public function getHistory(): array
    {
        if (\ilSession::has(self::KEY)) {
            return unserialize(\ilSession::get(self::KEY), ['allowed_classes' => false]);
        }
        return [];
    }
}
