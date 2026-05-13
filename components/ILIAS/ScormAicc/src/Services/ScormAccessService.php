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

use ILIAS\ScormAicc\Activities\ScormUserRelation;

class ScormAccessService
{
    public function __construct(
        private readonly ScormObjectResolver $scorm_object_resolver,
        private readonly ScormAccessChecker $scorm_access_checker,
    )
    {
    }

    public function canViewUserStatus(int $acting_usr_id, ScormUserRelation $relation): bool
    {
        return $this->getViewUserStatusAccessReport($acting_usr_id, $relation)['allowed'] === true;
    }

    public function canViewUserCertificateStatus(int $acting_usr_id, ScormUserRelation $relation): bool
    {
        return $this->getViewUserCertificateStatusAccessReport($acting_usr_id, $relation)['allowed'] === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewUserStatusAccessReport(int $acting_usr_id, ScormUserRelation $relation): array
    {
        return $this->buildAccessReport(
            'ViewScormUserStatusActivity',
            $acting_usr_id,
            $relation
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewUserCertificateStatusAccessReport(int $acting_usr_id, ScormUserRelation $relation): array
    {
        return $this->buildAccessReport(
            'ViewScormUserCertificateStatusActivity',
            $acting_usr_id,
            $relation
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccessReport(
        string $activity_name,
        int $acting_usr_id,
        ScormUserRelation $relation,
    ): array
    {
        $report = [
            'activity' => $activity_name,
            'allowed' => false,
            'reason' => '',
            'context' => [
                'acting_usr_id' => $acting_usr_id,
                'target_usr_id' => $relation->usr_id,
                'ref_id' => $relation->ref_id,
                'access_mode' => $acting_usr_id === $relation->usr_id ? 'self' : 'third_party',
            ],
            'object' => [
                'is_scorm_reference' => false,
                'obj_id' => null,
                'type' => null,
                'deleted' => null,
                'object_provider' => 'ilObject',
                'error' => null,
            ],
            'permissions' => [
                'access_provider' => $this->scorm_access_checker->getProviderName(),
                'visible' => false,
                'read' => false,
                'read_learning_progress' => false,
                'edit_learning_progress' => false,
            ],
            'rules' => [
                'self' => 'acting user may view own SCORM data with visible or read permission on the SCORM object.',
                'third_party' => 'acting user may view another user’s SCORM data with read_learning_progress, or with read and edit_learning_progress.',
            ],
        ];

        if (!$this->isValidUserId($acting_usr_id)) {
            $report['reason'] = 'The acting user id is invalid or anonymous.';

            return $report;
        }

        $object_report = $this->scorm_object_resolver->getScormReferenceReport($relation->ref_id);
        $report['object'] = $object_report;

        if ($object_report['is_scorm_reference'] !== true) {
            $report['reason'] = 'The given ref_id does not resolve to an active SCORM learning module.';

            return $report;
        }

        $permissions = [
            'access_provider' => $this->scorm_access_checker->getProviderName(),
            'visible' => $this->scorm_access_checker->checkAccessOfUser($acting_usr_id, 'visible', $relation->ref_id),
            'read' => $this->scorm_access_checker->checkAccessOfUser($acting_usr_id, 'read', $relation->ref_id),
            'read_learning_progress' => $this->scorm_access_checker->checkAccessOfUser($acting_usr_id, 'read_learning_progress', $relation->ref_id),
            'edit_learning_progress' => $this->scorm_access_checker->checkAccessOfUser($acting_usr_id, 'edit_learning_progress', $relation->ref_id),
        ];

        $report['permissions'] = $permissions;

        if ($acting_usr_id === $relation->usr_id) {
            if ($permissions['visible'] || $permissions['read']) {
                $report['allowed'] = true;
                $report['reason'] = 'Self access granted because the acting user has visible or read permission.';

                return $report;
            }

            $report['reason'] = 'Self access denied because the acting user has neither visible nor read permission.';

            return $report;
        }

        if ($permissions['read_learning_progress']) {
            $report['allowed'] = true;
            $report['reason'] = 'Third-party access granted because the acting user has read_learning_progress permission.';

            return $report;
        }

        if ($permissions['read'] && $permissions['edit_learning_progress']) {
            $report['allowed'] = true;
            $report['reason'] = 'Third-party access granted because the acting user has read and edit_learning_progress permission.';

            return $report;
        }

        $report['reason'] = 'Third-party access denied because the acting user has no sufficient learning-progress permission.';

        return $report;
    }

    private function isValidUserId(int $usr_id): bool
    {
        if ($usr_id <= 0) {
            return false;
        }

        if (defined('ANONYMOUS_USER_ID') && $usr_id === ANONYMOUS_USER_ID) {
            return false;
        }

        return true;
    }
}
