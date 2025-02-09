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

namespace ILIAS\LegalDocuments\test\ConsumerToolbox;

use ILIAS\Refinery\Transformation;
use ILIAS\LegalDocuments\test\ContainerMock;
use PHPUnit\Framework\TestCase;
use ILIAS\LegalDocuments\ConsumerToolbox\Convert;

require_once __DIR__ . '/../ContainerMock.php';

class ConvertTest extends TestCase
{
    use ContainerMock;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(Convert::class, new Convert($this->mock(Transformation::class), $this->mock(Transformation::class)));
    }

    public function testGetter(): void
    {
        $this->assertGetter(Convert::class, [
            'fromString' => $this->mock(Transformation::class),
            'toString' => $this->mock(Transformation::class),
        ]);
    }
}
