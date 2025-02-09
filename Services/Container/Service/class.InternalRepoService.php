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

namespace ILIAS\Container;

use ILIAS\Container\Classification\ClassificationSessionRepository;

/**
 * Repository internal repo service
 * @author Alexander Killing <killing@leifos.de>
 */
class InternalRepoService
{
    protected InternalDataService $data;
    protected \ilDBInterface $db;

    public function __construct(InternalDataService $data, \ilDBInterface $db)
    {
        $this->data = $data;
        $this->db = $db;
    }

    public function content(): Content\RepoService
    {
        return new Content\RepoService(
            $this->data,
            $this->db
        );
    }

    public function classification(int $base_ref_id): ClassificationSessionRepository
    {
        return new ClassificationSessionRepository(
            $base_ref_id
        );
    }
}
