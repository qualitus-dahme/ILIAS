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
use OutOfBoundsException;

class HasSCORMCertificateActivity extends ActivityImpl
{
    public function __construct(
        private readonly DataFactory $data_factory,
        private readonly ScormService $scorm_service,
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
            'Checks whether a certificate is available for a given user in a given SCORM learning module.'
        );
    }

    #[\Override]
    public function getInputDescription(): FormInput
    {
        global $DIC;

        return $DIC->ui()->factory()
            ->input()
            ->field()
            ->group(
                [
                    'ref_id' => $DIC->ui()->factory()
                        ->input()
                        ->field()
                        ->numeric(
                            'SCORM module ref_id',
                            'Reference id of the SCORM learning module.'
                        ),
                    'usr_id' => $DIC->ui()->factory()
                        ->input()
                        ->field()
                        ->numeric(
                            'User id',
                            'User id for which certificate availability shall be checked.'
                        ),
                ],
                'Has SCORM certificate'
            );
    }

    #[\Override]
    public function getOutputDescription(DescriptionFactory $f): Description
    {
        return $f->object(
            $this->markdown('Result of the SCORM certificate availability check.'),
            [
                'has_certificate' => $f->bool(
                    $this->markdown(
                        'True if a certificate is available for the given user in the given SCORM module.'
                    )
                ),
            ]
        );
    }

    #[\Override]
    public function isAllowedToPerform(int $usr_id, mixed $parameters): bool
    {
        return true;
    }

    #[\Override]
    public function perform(mixed $parameters): mixed
    {
        return [
            'has_certificate' => $this->scorm_service->hasSCORMCertificate(
                $parameters['ref_id'],
                $parameters['usr_id']
            ),
        ];
    }

    #[\Override]
    public function maybePerformAs(int $usr_id, array $raw_parameters): Result
    {
        try {
            $parameters = $this->normalizeParameters($raw_parameters);

            if (!$this->isAllowedToPerform($usr_id, $parameters)) {
                return $this->data_factory->error('Failed due to permissions.');
            }

            return $this->data_factory->ok($this->perform($parameters));
        } catch (InvalidArgumentException | OutOfBoundsException $e) {
            return $this->data_factory->error($e->getMessage());
        }
    }

    /**
     * @return array{ref_id: int, usr_id: int}
     */
    private function normalizeParameters(array $raw_parameters): array
    {
        return [
            'ref_id' => $this->readPositiveInt($raw_parameters, 'ref_id'),
            'usr_id' => $this->readPositiveInt($raw_parameters, 'usr_id'),
        ];
    }

    private function readPositiveInt(array $raw_parameters, string $key): int
    {
        if (!array_key_exists($key, $raw_parameters)) {
            throw new InvalidArgumentException('Missing parameter: ' . $key);
        }

        $value = $raw_parameters[$key];

        if (is_int($value)) {
            if ($value <= 0) {
                throw new InvalidArgumentException('Parameter must be greater than 0: ' . $key);
            }

            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            $int_value = (int) $value;
            if ($int_value <= 0) {
                throw new InvalidArgumentException('Parameter must be greater than 0: ' . $key);
            }

            return $int_value;
        }

        throw new InvalidArgumentException('Invalid integer parameter: ' . $key);
    }

    private function markdown(string $text): SimpleDocumentMarkdown
    {
        return $this->data_factory->text()->markdown()->simpleDocument($text);
    }
}
