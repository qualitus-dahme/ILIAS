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
use ilObjUserTracking;
use RuntimeException;

class ScormService
{
    public function __construct(
        private readonly ScormObjectResolver $scorm_object_resolver,
    )
    {
    }

    public function hasSCORMCertificate(int $ref_id, int $usr_id): bool
    {
        $obj_id = $this->scorm_object_resolver->getScormObjectIdByRefId($ref_id);

        $validator = new ilCertificateUserCertificateAccessValidator();

        return $validator->validate($usr_id, $obj_id);
    }

    /**
     * @return array{
     *     completion_status_available: bool,
     *     completion_status: string,
     *     reason: string
     * }
     */
    public function getSCORMCompletionStatusResult(int $ref_id, int $usr_id): array
    {
        $obj_id = $this->scorm_object_resolver->getScormObjectIdByRefId($ref_id);

        if (!ilObjUserTracking::_enabledLearningProgress()) {
            return [
                'completion_status_available' => false,
                'completion_status' => 'unavailable',
                'reason' => 'learning_progress_disabled',
            ];
        }

        return [
            'completion_status_available' => true,
            'completion_status' => $this->mapLearningProgressStatus(
                ilLPStatus::_lookupStatus($obj_id, $usr_id)
            ),
            'reason' => '',
        ];
    }

    public function getSCORMCompletionStatus(int $ref_id, int $usr_id): string
    {
        $result = $this->getSCORMCompletionStatusResult($ref_id, $usr_id);

        if ($result['completion_status_available'] !== true) {
            throw new RuntimeException('Learning progress not enabled in this installation. Aborting!');
        }

        return $result['completion_status'];
    }

    private function mapLearningProgressStatus(int $status): string
    {
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
}
