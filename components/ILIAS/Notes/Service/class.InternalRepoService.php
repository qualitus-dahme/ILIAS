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

namespace ILIAS\Notes;

/**
 * Notes internal repo service
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

    public function note(): NoteDBRepository
    {
        return new NoteDBRepository(
            $this->data,
            $this->db
        );
    }

    public function notesSession(): NotesSessionRepository
    {
        return new NotesSessionRepository();
    }

    public function settings(): NoteSettingsDBRepository
    {
        return new NoteSettingsDBRepository(
            $this->data,
            $this->db
        );
    }
}
