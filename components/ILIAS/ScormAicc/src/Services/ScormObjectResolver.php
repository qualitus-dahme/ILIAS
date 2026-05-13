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

use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use Throwable;

class ScormObjectResolver
{
    private const SCORM_OBJECT_TYPE = 'sahs';

    public function getScormObjectIdByRefId(int $ref_id): int
    {
        if ($ref_id <= 0) {
            throw new InvalidArgumentException('No ref_id given. Aborting!');
        }

        $reference_data = $this->lookupReferenceData($ref_id);

        if ($reference_data === null) {
            throw new OutOfBoundsException('No repository object found for ref_id: ' . $ref_id);
        }

        if ($this->isDeletedReference($reference_data)) {
            throw new OutOfBoundsException('Repository object with ref_id ' . $ref_id . ' has been deleted.');
        }

        if ($reference_data['type'] !== self::SCORM_OBJECT_TYPE) {
            throw new OutOfBoundsException('Repository object with ref_id ' . $ref_id . ' is not a SCORM learning module.');
        }

        return $reference_data['obj_id'];
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
            $reference_data = $this->lookupReferenceData($ref_id);

            if ($reference_data === null) {
                return [
                    'is_scorm_reference' => false,
                    'obj_id' => null,
                    'type' => null,
                    'deleted' => null,
                    'database_provider' => $this->getDatabaseProviderName(),
                    'error' => 'No repository object found for ref_id: ' . $ref_id,
                ];
            }

            return [
                'is_scorm_reference' => $reference_data['type'] === self::SCORM_OBJECT_TYPE
                    && !$this->isDeletedReference($reference_data),
                'obj_id' => $reference_data['obj_id'],
                'type' => $reference_data['type'],
                'deleted' => $reference_data['deleted'],
                'database_provider' => $this->getDatabaseProviderName(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'is_scorm_reference' => false,
                'obj_id' => null,
                'type' => null,
                'deleted' => null,
                'database_provider' => $this->getDatabaseProviderName(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return null|array{obj_id: int, type: string, deleted: mixed}
     */
    private function lookupReferenceData(int $ref_id): ?array
    {
        $db = $this->getDatabase();

        $query = '
            SELECT od.obj_id, od.type, obr.deleted
            FROM object_reference obr
            INNER JOIN object_data od ON od.obj_id = obr.obj_id
            WHERE obr.ref_id = ' . $db->quote($ref_id, 'integer');

        $result = $db->query($query);
        $row = $db->fetchAssoc($result);

        if (!is_array($row)) {
            return null;
        }

        return [
            'obj_id' => (int) $row['obj_id'],
            'type' => (string) $row['type'],
            'deleted' => $row['deleted'] ?? null,
        ];
    }

    private function getDatabase(): object
    {
        global $DIC;
        global $ilDB;

        if (is_object($DIC) && method_exists($DIC, 'database')) {
            try {
                return $DIC->database();
            } catch (Throwable) {
            }
        }

        if (is_object($ilDB)) {
            return $ilDB;
        }

        throw new RuntimeException('No database service available. Neither $DIC->database() nor $ilDB could be used.');
    }

    private function getDatabaseProviderName(): string
    {
        global $DIC;
        global $ilDB;

        if (is_object($DIC) && method_exists($DIC, 'database')) {
            return '$DIC->database()';
        }

        if (is_object($ilDB)) {
            return '$ilDB';
        }

        return 'none';
    }

    /**
     * @param array{obj_id: int, type: string, deleted: mixed} $reference_data
     */
    private function isDeletedReference(array $reference_data): bool
    {
        if (!array_key_exists('deleted', $reference_data)) {
            return false;
        }

        if ($reference_data['deleted'] === null) {
            return false;
        }

        if ($reference_data['deleted'] === '' || $reference_data['deleted'] === '0' || $reference_data['deleted'] === 0) {
            return false;
        }

        return true;
    }
}
