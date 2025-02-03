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

use ILIAS\Filesystem\Filesystem;

/**
 * Import directory interface
 * @author     Stefan Meyer <smeyer.ilias@gmx.de>
 * @ingroup    ServicesExport
 */
class ilScormImportDirectory extends ilImportDirectory
{
    private const PATH_PREFIX = 'learningModule';

    protected function getPathPrefix(): string
    {
        return self::PATH_PREFIX;
    }
}
