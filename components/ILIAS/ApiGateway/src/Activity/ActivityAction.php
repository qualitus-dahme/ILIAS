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

namespace ILIAS\ApiGateway\Activity;

use DomainException;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\Component\Activities\Activity;
use ILIAS\UI\Component\Input\Factory as InputFactory;
use RuntimeException;
use Throwable;

class ActivityAction implements Action
{
    private const int GUEST_USER_ID = 0;

    public function __construct(
        private readonly Activity $activity,
        private readonly InputFactory $inputFactory,
    ) {
    }

    #[\Override]
    public function __invoke(array $params, ?AuthUser $user): mixed
    {
        $userId = $user ? $user->getId() : self::GUEST_USER_ID;

        $result = $this->activity->maybePerformAs(
            $this->inputFactory,
            $userId,
            $params
        );

        if ($result->isError()) {
            $error = $result->error();

            if ($error instanceof Throwable) {
                throw $error;
            }

            throw new DomainException($error);
        }

        $value = $result->value();

        $factory = new \ILIAS\Data\Description\Factory();
        $output = $this->activity->getOutputDescription($factory);

        if (!$output->matches($value)) {
            throw new RuntimeException('Output description does not match result.');
        }

        return $output->getPrimitiveRepresentation($value);
    }
}
