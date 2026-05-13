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

namespace ILIAS\ScormAicc\Services;

use ilObject;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;

class ScormObjectResolver
{
    private const SCORM_OBJECT_TYPE = 'sahs';

    public function getScormObjectIdByRefId(int $ref_id): int
    {
        if ($ref_id <= 0) {
            throw new InvalidArgumentException('No ref_id given. Aborting!');
        }

        $obj_id = (int) ilObject::_lookupObjectId($ref_id);
        if ($obj_id <= 0) {
            throw new OutOfBoundsException('No repository object found for ref_id: ' . $ref_id);
        }

        if (ilObject::_isInTrash($ref_id)) {
            throw new OutOfBoundsException('Repository object with ref_id ' . $ref_id . ' has been deleted.');
        }

        if (ilObject::_lookupType($obj_id) !== self::SCORM_OBJECT_TYPE) {
            throw new OutOfBoundsException('Repository object with ref_id ' . $ref_id . ' is not a SCORM learning module.');
        }

        return $obj_id;
    }

    public function isScormReference(int $ref_id): bool
    {
        try {
            $this->getScormObjectIdByRefId($ref_id);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getScormReferenceReport(int $ref_id): array
    {
        try {
            $obj_id = $this->getScormObjectIdByRefId($ref_id);

            return [
                'is_scorm_reference' => true,
                'obj_id' => $obj_id,
                'type' => ilObject::_lookupType($obj_id),
                'deleted' => ilObject::_isInTrash($ref_id),
                'object_provider' => 'ilObject',
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'is_scorm_reference' => false,
                'obj_id' => null,
                'type' => null,
                'deleted' => null,
                'object_provider' => 'ilObject',
                'error' => $e->getMessage(),
            ];
        }
    }
}
