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

namespace ILIAS\ApiGateway\Application\Factory;

use ILIAS\ApiGateway\Configuration\Domain\Configuration;
use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;

readonly class HttpConfigFactory
{
    public function __construct(
        private Configuration $configuration,
    ) {
    }

    public function createAuthConfig(): AuthConfig
    {
        return new AuthConfig(
            $this->configuration->getClientId(),
            $this->configuration->getSecretKey(),
            $this->configuration->getEncryption(),
            $this->configuration->getHashing(),
            $this->configuration->getAccessTokenExpiry(),
            $this->configuration->getRefreshTokenExpiry(),
        );
    }

    public function createWebConfig(ServiceProtocol $protocol): WebConfig
    {
        return new WebConfig(
            $this->configuration->getBaseUrl(),
            $protocol,
            $this->configuration->isEnabled($protocol),
            $this->configuration->isDebugEnabled(),
            $this->configuration->isLoggingEnabled(),
            $this->configuration->isLoggingDetailsEnabled(),
        );
    }
}
