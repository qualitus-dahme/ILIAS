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

namespace ILIAS\ScormAicc\Activities;

use ILIAS\Component\Activities\ActivityImpl;
use ILIAS\Component\Activities\ActivityType;
use ILIAS\Data\Description\Description;
use ILIAS\Data\Description\Factory as DescriptionFactory;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\Result;
use ILIAS\Data\Text\SimpleDocumentMarkdown;
use ILIAS\ScormAicc\Services\ScormAccessService;
use ILIAS\ScormAicc\Services\ScormService;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Factory as InputFactory;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use InvalidArgumentException;
use Throwable;

class ViewScormUserCertificateStatusActivity extends ActivityImpl
{
    public function __construct(
        private readonly DataFactory $data_factory,
        private readonly ScormService $scorm_service,
        private readonly ScormAccessService $scorm_access_service,
    ) {
    }

    #[\Override]
    public function getType(): ActivityType
    {
        return ActivityType::Query;
    }

    #[\Override]
    public function getDescription(): SimpleDocumentMarkdown
    {
        return $this->markdown(
            'Shows whether a certificate is available for a user in a SCORM learning module.'
        );
    }

    #[\Override]
    public function getInputDescription(FieldFactory $f): FormInput
    {
        return $f->group(
            [
                'ref_id' => $f->numeric(
                    'SCORM module ref_id',
                    'Reference id of the SCORM learning module.'
                )->withDedicatedName('ref_id'),
                'usr_id' => $f->numeric(
                    'User id',
                    'User id for which certificate availability shall be shown.'
                )->withDedicatedName('usr_id'),
            ],
            'SCORM/user-relation'
        );
    }

    #[\Override]
    public function getOutputDescription(DescriptionFactory $f): Description
    {
        return $f->object(
            $this->markdown(
                'Certificate availability for a user in a SCORM learning module.'
            ),
            [
                'has_certificate' => $f->bool(
                    $this->markdown(
                        'True if a certificate is available for the given user in the given SCORM learning module.'
                    )
                ),
            ]
        );
    }

    #[\Override]
    public function isAllowedToPerform(int $usr_id, mixed $parameters): bool
    {
        if (!$parameters instanceof ScormUserRelation) {
            return false;
        }

        return $this->scorm_access_service
            ->canViewUserCertificateStatus(
                $usr_id,
                $parameters
            );
    }

    #[\Override]
    public function perform(mixed $parameters): mixed
    {
        if (!$parameters instanceof ScormUserRelation) {
            throw new InvalidArgumentException(
                ScormUserRelation::class . ' expected.'
            );
        }

        return [
            'has_certificate' => $this->scorm_service->hasSCORMCertificate(
                $parameters->ref_id,
                $parameters->usr_id
            ),
        ];
    }

    #[\Override]
    public function maybePerformAs(
        InputFactory $input_factory,
        int $usr_id,
        array $raw_parameters
    ): Result {
        try {
            $parameters = $this->readParameters($raw_parameters);

            if (!$this->isAllowedToPerform($usr_id, $parameters)) {
                return $this->data_factory->error(
                    'Failed due to permissions.'
                );
            }

            return $this->data_factory->ok(
                $this->perform($parameters)
            );
        } catch (Throwable $e) {
            return $this->data_factory->error(
                $e->getMessage()
            );
        }
    }

    private function readParameters(array $raw_parameters): ScormUserRelation
    {
        return new ScormUserRelation(
            (int) ($raw_parameters['ref_id'] ?? 0),
            (int) ($raw_parameters['usr_id'] ?? 0)
        );
    }

    private function markdown(string $text): SimpleDocumentMarkdown
    {
        return $this->data_factory
            ->text()
            ->markdown()
            ->simpleDocument($text);
    }
}
