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

namespace ILIAS\Wiki\Navigation;

/**
 * Wiki page
 */
class ImportantPage
{
    protected int $id;
    protected int $order;
    protected int $indent;

    public function __construct(
        int $id,
        int $order,
        int $indent
    ) {
        $this->id = $id;
        $this->order = $order;
        $this->indent = $indent;
    }

    public function getId(): int
    {
        return $this->id;
    }
    public function getOrder(): int
    {
        return $this->order;
    }
    public function getIndent(): int
    {
        return $this->indent;
    }
}
