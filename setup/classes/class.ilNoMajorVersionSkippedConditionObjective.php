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

use ILIAS\Setup;
use ILIAS\Data;

class ilNoMajorVersionSkippedConditionObjective implements Setup\Objective
{
    protected Data\Factory $data_factory;

    public function __construct(Data\Factory $data_factory)
    {
        $this->data_factory = $data_factory;
    }

    public function getHash(): string
    {
        return hash(
            "sha256",
            get_class($this)
        );
    }

    public function getLabel(): string
    {
        return "No major version is skipped in update.";
    }

    public function isNotable(): bool
    {
        return true;
    }

    public function getPreconditions(Setup\Environment $environment): array
    {
        return [
            new ilSettingsFactoryExistsObjective()
        ];
    }

    public function achieve(Setup\Environment $environment): Setup\Environment
    {
        $factory = $environment->getResource(Setup\Environment::RESOURCE_SETTINGS_FACTORY);
        $settings = $factory->settingsFor("common");

        $current_version_string = $settings->get(ilVersionWrittenToSettingsObjective::ILIAS_VERSION_KEY);
        $current_version = $this->data_factory->version($current_version_string);
        $target_version = $this->data_factory->version(ILIAS_VERSION_NUMERIC);

        if (($target_version->getMajor() - $current_version->getMajor()) > 1) {
            throw new Setup\NotExecutableException(
                "Updates may only be performed from one major version to the " .
                "next, no major versions may be skipped."
            );
        }

        return $environment;
    }

    public function isApplicable(Setup\Environment $environment): bool
    {
        return true;
    }
}
