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

namespace ILIAS\Exercise\TutorFeedbackFile;

use ILIAS\Exercise\IRSS\CollectionWrapper;
use ILIAS\ResourceStorage\Collection\ResourceCollection;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;

interface TutorFeedbackFileRepositoryInterface
{
    public function createCollection(int $ass_id, int $user_id): void;

    public function getIdStringForAssIdAndUserId(int $ass_id, int $user_id): string;

    public function hasCollection(int $ass_id, int $user_id): bool;

    public function getCollection(int $ass_id, int $user_id): ?ResourceCollection;

    public function getCollectionResourcesInfo(
        int $ass_id,
        int $user_id
    ): \Generator;

    public function deleteCollection(
        int $ass_id,
        int $user_id,
        ResourceStakeholder $stakeholder
    ): void;

    public function getParticipantIdForRcid(int $ass_id, string $rcid): int;

    public function getFilenameForRid(int $ass_id, int $part_id, string $rid): string;

}
