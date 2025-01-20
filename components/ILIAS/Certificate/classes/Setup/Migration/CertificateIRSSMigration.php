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

namespace ILIAS\Certificate\Setup\Migration;

use ilDBConstants;
use ilDBInterface;
use ReflectionClass;
use ilDatabaseException;
use ILIAS\Setup\Migration;
use ILIAS\Setup\Environment;
use ilDatabaseUpdatedObjective;
use ilResourceStorageMigrationHelper;
use ILIAS\Certificate\File\ilCertificateTemplateStakeholder;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class CertificateIRSSMigration implements Migration
{
    public const NUMBER_OF_STEPS = 10;
    public const NUMBER_OF_PATHS_PER_STEP = 10;
    private ilResourceStorageMigrationHelper $helper;
    private ilDBInterface $db;
    private ilCertificateTemplateStakeholder $stakeholder;

    public function getLabel(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function getDefaultAmountOfStepsPerRun(): int
    {
        return self::NUMBER_OF_STEPS;
    }

    public function getPreconditions(Environment $environment): array
    {
        return [
            new ilDatabaseUpdatedObjective()
        ];
    }

    public function prepare(Environment $environment): void
    {
        $this->db = $environment->getResource(Environment::RESOURCE_DATABASE);
        $this->helper = new ilResourceStorageMigrationHelper(new ilCertificateTemplateStakeholder(), $environment);
        $this->stakeholder = new ilCertificateTemplateStakeholder();
    }

    /**
     * @throws ilDatabaseException
     */
    public function step(Environment $environment): void
    {
        $this->migrateGlobalCertificateBackgroundImage(true);
        $remaining_paths = $this->stepUserCertificates(self::NUMBER_OF_PATHS_PER_STEP);
        if ($remaining_paths > 0) {
            $this->stepTemplateCertificates($remaining_paths);
        }
    }

    public function stepUserCertificates(int $remaining_paths): int
    {
        $this->db->setLimit($remaining_paths);
        $query = '
            SELECT path
            FROM (
                     SELECT id, background_image_path AS path FROM il_cert_user_cert
                            WHERE background_image_ident IS NULL OR background_image_ident = \'\'
                     UNION ALL
                     SELECT id, thumbnail_image_path AS path FROM il_cert_user_cert
                            WHERE thumbnail_image_ident IS NULL OR thumbnail_image_ident = \'\'
                 ) AS t
            GROUP BY path
            HAVING path IS NOT NULL AND path != \'\'
        ';
        $result = $this->db->query($query);
        $paths = $this->db->numRows($result);
        if ($paths > 0) {
            while ($row = $this->db->fetchAssoc($result)) {
                $this->updateCertificatePathFromTable($row['path'] ?? '', 'il_cert_user_cert');
            }
            $remaining_paths -= self::NUMBER_OF_PATHS_PER_STEP - $paths;
        }

        return $remaining_paths;
    }

    public function migrateGlobalCertificateBackgroundImage(bool $hotrun = false): int
    {
        $result = $this->db->queryF(
            'SELECT value FROM settings WHERE module = %s AND keyword = %s',
            [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT],
            ['certificate', 'cert_bg_image']
        );
        $row = $this->db->fetchAssoc($result);
        if (isset($row['value']) && $row['value'] !== '' && is_file(
            ILIAS_ABSOLUTE_PATH . '/' . ILIAS_WEB_DIR . '/' . CLIENT_ID . '/certificates/default/' . $row['value']
        )) {
            if ($hotrun) {
                $resource_id = $this->helper->movePathToStorage(
                    ILIAS_ABSOLUTE_PATH . '/' . ILIAS_WEB_DIR . '/' . CLIENT_ID . '/certificates/default/' . $row['value'],
                    $this->stakeholder->getOwnerOfNewResources(),
                    null,
                    null,
                    true
                );
                $image_ident = '-';
                if ($resource_id instanceof ResourceIdentification) {
                    $image_ident = $resource_id->serialize();
                }

                $this->updateDefaultBackgroundImagePaths(
                    '/certificates/default/' . $row['value'],
                    $image_ident
                );

                $query = '
                        UPDATE settings
                        SET value = %s
                        WHERE module = %s AND keyword = %s';
                $this->db->manipulateF(
                    $query,
                    [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT, ilDBConstants::T_TEXT],
                    [$image_ident, 'certificate', 'cert_bg_image']
                );

                return 0;
            }

            return 1;
        }

        return 0;
    }

    public function stepTemplateCertificates(int $remaining_paths): int
    {
        $this->db->setLimit($remaining_paths);
        $query = '
            SELECT path
            FROM (
                     SELECT id, background_image_path AS path FROM il_cert_template
                            WHERE background_image_ident IS NULL OR background_image_ident = \'\' 
                     UNION ALL
                     SELECT id, thumbnail_image_path AS path FROM il_cert_template
                            WHERE thumbnail_image_ident IS NULL OR thumbnail_image_ident = \'\'
                 ) AS t
            GROUP BY path
            HAVING path IS NOT NULL AND path != \'\'
        ';
        $result = $this->db->query($query);
        $paths = $this->db->numRows($result);
        if ($paths > 0) {
            while ($row = $this->db->fetchAssoc($result)) {
                $this->updateCertificatePathFromTable($row['path'] ?? '', 'il_cert_template');
            }
            $remaining_paths -= self::NUMBER_OF_PATHS_PER_STEP - $paths;
        }

        return $remaining_paths;
    }

    public function updateCertificatePathFromTable(string $filepath, string $table): void
    {
        if (!$filepath) {
            return;
        }

        $resource_id = $this->helper->movePathToStorage(
            ILIAS_ABSOLUTE_PATH . '/' . ILIAS_WEB_DIR . '/' . CLIENT_ID . $filepath,
            $this->stakeholder->getOwnerOfNewResources(),
            null,
            null,
            true
        );

        $image_ident = '-';
        if ($resource_id instanceof ResourceIdentification) {
            $image_ident = $resource_id->serialize();
        }

        $query = "
                UPDATE {$this->db->quoteIdentifier($table)}
                SET background_image_ident = %s WHERE background_image_path = %s;";
        $this->db->manipulateF(
            $query,
            [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT],
            [$image_ident, $filepath]
        );
        $query = "
                UPDATE {$this->db->quoteIdentifier($table)}
                SET thumbnail_image_ident = %s WHERE thumbnail_image_path = %s;";
        $this->db->manipulateF(
            $query,
            [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT],
            [$image_ident, $filepath]
        );
        if ($image_ident !== '-') {
            $query = "
                    UPDATE {$this->db->quoteIdentifier($table)}
                    SET background_image_path = NULL WHERE background_image_path = %s;";
            $this->db->manipulateF(
                $query,
                [ilDBConstants::T_TEXT],
                [$filepath]
            );
            $query = "
                    UPDATE {$this->db->quoteIdentifier($table)}
                    SET thumbnail_image_path = NULL WHERE thumbnail_image_path = %s;";
            $this->db->manipulateF(
                $query,
                [ilDBConstants::T_TEXT],
                [$filepath]
            );
        }
    }

    public function getRemainingAmountOfSteps(): int
    {
        $paths = $this->migrateGlobalCertificateBackgroundImage(false);

        $result = $this->db->query(
            '
                    SELECT COUNT(*) AS count FROM (
                    SELECT path
                    FROM (
                        SELECT id, background_image_path AS path FROM il_cert_user_cert
                                             WHERE background_image_ident IS NULL OR background_image_ident = \'\'
                        UNION ALL
                        SELECT id, thumbnail_image_path AS path FROM il_cert_user_cert
                                             WHERE thumbnail_image_ident IS NULL OR thumbnail_image_ident = \'\'
                    ) AS t
                    GROUP BY path
                    HAVING path IS NOT NULL AND path != \'\') AS t;
            '
        );
        $row = $this->db->fetchAssoc($result);

        $paths += (int) ($row['count'] ?? 0);

        $result = $this->db->query(
            '
                    SELECT COUNT(*) AS count FROM (
                    SELECT path
                    FROM (
                        SELECT id, background_image_path AS path FROM il_cert_template
                                             WHERE background_image_ident IS NULL OR background_image_ident = \'\'
                        UNION ALL
                        SELECT id, thumbnail_image_path AS path FROM il_cert_template
                                             WHERE thumbnail_image_ident IS NULL OR thumbnail_image_ident = \'\'
                    ) AS t
                    GROUP BY path
                    HAVING path IS NOT NULL AND path != \'\') AS t;
            '
        );
        $row = $this->db->fetchAssoc($result);

        $paths += (int) ($row['count'] ?? 0);

        return (int) ceil($paths / self::NUMBER_OF_STEPS);
    }

    public function updateDefaultBackgroundImagePaths(string $old_relative_path, string $new_rid): void
    {
        $this->db->manipulateF(
            '
                    UPDATE il_cert_template SET background_image_ident = %s 
                        WHERE currently_active = 1 AND (background_image_path = %s OR background_image_path = %s )
                        AND background_image_ident IS NULL OR background_image_ident = \'\'',
            [
                ilDBConstants::T_TEXT,
                ilDBConstants::T_TEXT,
                ilDBConstants::T_TEXT
            ],
            [
                $new_rid,
                $old_relative_path,
                '/certificates/default/background.jpg'
            ]
        );

        $this->db->manipulateF(
            '
                    UPDATE il_cert_user_cert SET background_image_ident = %s 
                         WHERE currently_active = 1 AND (background_image_path = %s OR background_image_path = %s )
                         AND background_image_ident IS NULL OR background_image_ident = \'\'',
            [
                ilDBConstants::T_TEXT,
                ilDBConstants::T_TEXT,
                ilDBConstants::T_TEXT
            ],
            [
                $new_rid,
                $old_relative_path,
                '/certificates/default/background.jpg'
            ]
        );
    }
}
