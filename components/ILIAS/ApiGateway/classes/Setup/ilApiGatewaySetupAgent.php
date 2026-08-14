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

namespace ILIAS\ApiGateway\Setup;

use ilDatabaseUpdateStepsExecutedObjective;
use ilDatabaseUpdateStepsMetricsCollectedObjective;
use ILIAS\ApiGateway\Setup\Objectives\ApiGatewayMetricsCollectedObjective;
use ILIAS\ApiGateway\Setup\Steps\ApiGatewayDBUpdateSteps;
use ILIAS\Setup;
use ilObjApiGateway;
use ilTreeAdminNodeAddedObjective;
use Override;

class ilApiGatewaySetupAgent extends Setup\Agent\NullAgent
{
    #[Override]
    public function getUpdateObjective(?Setup\Config $config = null): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Database is updated for component/ILIAS/ApiGateway',
            true,
            new ilTreeAdminNodeAddedObjective(ilObjApiGateway::TYPE, 'ApiGateway'),
            new ilDatabaseUpdateStepsExecutedObjective(new ApiGatewayDBUpdateSteps()),
        );
    }

    #[Override]
    public function getStatusObjective(Setup\Metrics\Storage $storage): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Component ApiGateway',
            true,
            // component info & settings
            new ApiGatewayMetricsCollectedObjective(
                $storage,
            ),
            // database migrations
            new ilDatabaseUpdateStepsMetricsCollectedObjective(
                $storage,
                new ApiGatewayDBUpdateSteps(),
            ),
        );
    }
}
