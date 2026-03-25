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

class HelloSCORMActivity extends ActivityImpl
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
            'Simple smoke test activity for the SCORM component.'
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
                [],
                'Hello SCORM'
            );
    }

    #[\Override]
    public function getOutputDescription(DescriptionFactory $f): Description
    {
        return $f->object(
            $this->markdown('Result of the SCORM hello activity.'),
            [
                'message' => $f->string(
                    $this->markdown('Simple greeting returned by the SCORM component.')
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
            'message' => $this->scorm_service->hello(),
        ];
    }

    #[\Override]
    public function maybePerformAs(int $usr_id, array $raw_parameters): Result
    {
        if (!$this->isAllowedToPerform($usr_id, [])) {
            return $this->data_factory->error('Failed due to permissions.');
        }

        return $this->data_factory->ok($this->perform([]));
    }

    private function markdown(string $text): SimpleDocumentMarkdown
    {
        return $this->data_factory->text()->markdown()->simpleDocument($text);
    }
}
