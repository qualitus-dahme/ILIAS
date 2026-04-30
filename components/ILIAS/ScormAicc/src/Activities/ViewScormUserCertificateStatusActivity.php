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
use ILIAS\ScormAicc\Services\ScormService;
use ILIAS\UI\Component\Input\Control\Form\FormInput;
use InvalidArgumentException;
use Throwable;

class ViewScormUserCertificateStatusActivity extends ActivityImpl
{
    public function __construct(
        private readonly DataFactory $data_factory,
        private readonly ScormService $scorm_service,
    )
    {
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
    public function getInputDescription(): FormInput
    {
        global $DIC;

        $field = $DIC->ui()->factory()->input()->field();
        $refinery = $DIC->refinery();

        return $field->group(
            [
                $field->numeric(
                    'SCORM module ref_id',
                    'Reference id of the SCORM learning module.'
                )
                    ->withDedicatedName('ref_id')
                    ->withAdditionalTransformation(
                        $refinery->int()->greaterThan(0)
                    ),
                $field->numeric(
                    'User id',
                    'User id for which certificate availability shall be shown.'
                )
                    ->withDedicatedName('usr_id')
                    ->withAdditionalTransformation(
                        $refinery->int()->greaterThan(0)
                    ),
            ],
            'SCORM/user-relation'
        )->withAdditionalTransformation(
            $refinery->to()->toNew(ScormUserRelation::class)
        );
    }

    #[\Override]
    public function getOutputDescription(DescriptionFactory $f): Description
    {
        return $f->object(
            $this->markdown('Certificate availability for a user in a SCORM learning module.'),
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

        /*
         * TODO: Replace this temporary implementation with the access check used
         * by the SCORM GUI or the underlying service layer.
         */
        return true;
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
    public function maybePerformAs(int $usr_id, array $raw_parameters): Result
    {
        try {
            $parameters = $this->readParameters($raw_parameters);

            if (!$this->isAllowedToPerform($usr_id, $parameters)) {
                return $this->data_factory->error('Failed due to permissions.');
            }

            return $this->data_factory->ok($this->perform($parameters));
        } catch (Throwable $e) {
            return $this->data_factory->error($e->getMessage());
        }
    }

    private function readParameters(array $raw_parameters): ScormUserRelation
    {
        /*
         * TODO: Replace this method with the standard ActivityImpl implementation
         * once maybePerformAs validates raw parameters through getInputDescription().
         */
        return new ScormUserRelation(
            (int) ($raw_parameters['ref_id'] ?? 0),
            (int) ($raw_parameters['usr_id'] ?? 0)
        );
    }

    private function markdown(string $text): SimpleDocumentMarkdown
    {
        return $this->data_factory->text()->markdown()->simpleDocument($text);
    }
}
