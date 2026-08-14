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

namespace ILIAS\ApiGateway\Configuration\Domain;

use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;

interface Configuration
{
    public function getBaseUrl(): string;

    public function getClientId(): string;

    public function getSecretKey(): string;

    public function getEncryption(): string;

    public function getHashing(): string;

    public function getAccessTokenExpiry(): int;

    public function getRefreshTokenExpiry(): int;

    public function isEnabled(ServiceProtocol $protocol): bool;

    public function isDocsEnabled(ServiceProtocol $protocol): bool;

    public function isDebugEnabled(): bool;

    public function isLoggingEnabled(): bool;

    public function isLoggingDetailsEnabled(): bool;

}
