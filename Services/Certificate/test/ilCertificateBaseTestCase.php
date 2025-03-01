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

use ILIAS\DI\Container;
use PHPUnit\Framework\TestCase;
use ILIAS\LegalDocuments\Conductor;

/**
 * Class ilCertificateBaseTestCase
 * @author Michael Jansen <mjansen@databay.de>
 */
abstract class ilCertificateBaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ANONYMOUS_USER_ID')) {
            define('ANONYMOUS_USER_ID', 13);
        }

        global $DIC;

        $this->dic = is_object($DIC) ? clone $DIC : $DIC;

        $DIC = new Container();

        parent::setUp();
    }

    protected function setGlobalVariable(string $name, $value): void
    {
        global $DIC;

        $GLOBALS[$name] = $value;

        unset($DIC[$name]);
        $DIC[$name] = static function (Container $c) use ($name) {
            return $GLOBALS[$name];
        };
    }
}
