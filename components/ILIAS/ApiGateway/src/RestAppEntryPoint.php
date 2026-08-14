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

namespace ILIAS\ApiGateway;

use ilContext;
use ILIAS\ApiGateway\Application\Factory\WebAppFactory;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\Component\EntryPoint;
use Override;

class RestAppEntryPoint implements EntryPoint
{
    public function __construct(
        protected WebAppFactory $webAppFactory,
    ) {
        //
    }

    #[Override]
    public function getName(): string
    {
        return self::class;
    }

    #[Override]
    public function enter(): int
    {
        ilContext::init(ilContext::CONTEXT_REST);

        entry_point('ILIAS Legacy Initialisation Adapter'); // to initialize legacy dependencies

        $this->webAppFactory->create(ServiceProtocol::REST)->run();

        return 0;
    }
}
