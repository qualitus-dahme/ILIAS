<?php

declare(strict_types=1);

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

namespace ILIAS\BookingManager\Setup;

use ILIAS\Setup;

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class Agent extends Setup\Agent\NullAgent
{
    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Booking Manager Update',
            true,
            new \ilDatabaseUpdateStepsExecutedObjective(new ilBookingManagerDBUpdateSteps()),
            new \ilDatabaseUpdateStepsExecutedObjective(new ilBookingManager8HotfixDBUpdateSteps())
        );
    }
}
