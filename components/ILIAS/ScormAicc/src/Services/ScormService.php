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

use ilCertificateUserCertificateAccessValidator;
use ilLPStatus;
use ilObject;
use ilObjUserTracking;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;

class ScormService
{
    public function hasSCORMCertificate(int $ref_id, int $usr_id): bool
    {
        $obj_id = $this->getScormObjectIdByRefId($ref_id);

        $validator = new ilCertificateUserCertificateAccessValidator();

        return $validator->validate($usr_id, $obj_id);
    }

    public function getSCORMCompletionStatus(int $ref_id, int $usr_id): string
    {
        $obj_id = $this->getScormObjectIdByRefId($ref_id);

        if (!ilObjUserTracking::_enabledLearningProgress()) {
            throw new RuntimeException('Learning progress not enabled in this installation. Aborting!');
        }

        $status = ilLPStatus::_lookupStatus($obj_id, $usr_id);

        if ($status === ilLPStatus::LP_STATUS_COMPLETED_NUM) {
            return 'completed';
        }

        if ($status === ilLPStatus::LP_STATUS_FAILED_NUM) {
            return 'failed';
        }

        if ($status === ilLPStatus::LP_STATUS_IN_PROGRESS_NUM) {
            return 'in_progress';
        }

        return 'not_attempted';
    }

    public function hello(): string
    {
        return 'Hello from ScormAicc.';
    }

    /**
     * @return array{component: string, status: string, implemented_activities: string}
     */
    public function getServiceInfo(): array
    {
        return [
            'component' => 'ILIAS\\ScormAicc',
            'status' => 'ok',
            'implemented_activities' => implode(', ', [
                'HasSCORMCertificateActivity',
                'GetSCORMCompletionStatusActivity',
                'HelloSCORMActivity',
                'GetSCORMServiceInfoActivity',
            ]),
        ];
    }

    private function getScormObjectIdByRefId(int $ref_id): int
    {
        if ($ref_id <= 0) {
            throw new InvalidArgumentException('No ref_id given. Aborting!');
        }

        $obj_id = (int) ilObject::_lookupObjectId($ref_id);
        if ($obj_id <= 0) {
            throw new OutOfBoundsException('No scorm module found for id: ' . $ref_id);
        }

        if (ilObject::_isInTrash($ref_id)) {
            throw new OutOfBoundsException('SCORM learning module with ref id ' . $ref_id . ' has been deleted.');
        }

        return $obj_id;
    }
}
