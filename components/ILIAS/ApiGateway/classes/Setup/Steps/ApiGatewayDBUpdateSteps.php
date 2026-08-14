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

namespace ILIAS\ApiGateway\Setup\Steps;

class ApiGatewayDBUpdateSteps implements \ilDatabaseUpdateSteps
{
    private const string TABLE_NAME_REFRESH = 'apig_refresh_tokens';
    /** @var \ilDBPdo */
    protected \ilDBInterface $db;

    public function prepare(\ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function step_1(): void
    {
        if ($this->db->tableExists(self::TABLE_NAME_REFRESH)) {
            return;
        }

        $fields = [
            'id' => [
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => 0,
            ],
            'user_id' => [
                'type' => 'integer',
                'length' => 8,
                'notnull' => true,
                'default' => 0,
            ],
            'token_hash' => [
                'type' => 'text',
                'length' => 255,
                'notnull' => true,
            ],
            'is_revoked' => [
                'type' => 'integer',
                'length' => 1,
                'notnull' => true,
                'default' => 0,
            ],
            'expires_at' => [
                'type' => 'timestamp',
                'notnull' => true,
                'default' => '',
            ],
            'created_at' => [
                'type' => 'timestamp',
                'notnull' => true,
                'default' => '',
            ],
            'updated_at' => [
                'type' => 'timestamp',
                'notnull' => true,
                'default' => '',
            ],
        ];

        $this->db->createTable(self::TABLE_NAME_REFRESH, $fields);
        $this->db->addPrimaryKey(self::TABLE_NAME_REFRESH, ['id']);
        $this->db->createSequence(self::TABLE_NAME_REFRESH);
        $this->db->addUniqueConstraint(self::TABLE_NAME_REFRESH, ['token_hash'], 'th1');
        $this->db->addIndex(self::TABLE_NAME_REFRESH, ['user_id'], 'usr');
        $this->db->addIndex(self::TABLE_NAME_REFRESH, ['expires_at'], 'exp');

        return;
    }
}
