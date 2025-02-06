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

/**
 * @deprecated Will be removed in ILIAS 10. Use ILIAS ResourceStorageService as replacement.
 */
class ilFileDataImport extends ilFileData
{
    protected string $import_path;

    public function __construct()
    {
        define('IMPORT_PATH', 'import');
        parent::__construct();
        $this->import_path = parent::getPath() . "/" . IMPORT_PATH;
        $this->initExportDirectory();
    }

    #[\Override]
    public function getPath(): string
    {
        return $this->import_path;
    }

    private function initExportDirectory(): void
    {
        if (!file_exists($this->import_path)) {
            ilFileUtils::makeDir($this->import_path);
        }
    }
}
