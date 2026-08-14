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

namespace ILIAS\ApiGateway\Configuration\Domain\Enum;

enum SystemSetting: string
{
    case AUTH_SECRET_KEY = 'auth_secret_key';
    case AUTH_ALGO_ENCRYPTION = 'auth_algo_encryption';
    case AUTH_ALGO_HASH = 'auth_algo_hash';
    case AUTH_TOKEN_EXPIRY_ACCESS = 'auth_token_expiry_access';
    case AUTH_TOKEN_EXPIRY_REFRESH = 'auth_token_expiry_refresh';
    case REST_WS_ENABLED = 'rest_ws_enabled';
    case REST_DOCS_ENABLED = 'rest_docs_enabled';
}
