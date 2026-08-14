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

use function strlen;

readonly class ActivityNamespace
{
    private const string NEEDLE_QUERY = 'Query';
    private const string NEEDLE_GET = 'Get';
    private const string NEEDLE_ACTIVITY = 'Activity';

    /** @var string[] */
    private const array CORE_VENDORS = ['ilias'];
    private string $path;

    public function __construct(
        private string $vendor,
        private string $component,
        private string $name,
    ) {
        $vendor = strtolower($this->vendor);
        $vendor = \in_array($vendor, self::CORE_VENDORS, true) ? '' : $vendor;
        $component = strtolower($this->component);
        $subject = ucfirst($this->name);

        if (str_starts_with($subject, self::NEEDLE_QUERY)) {
            $subject = substr($subject, strlen(self::NEEDLE_QUERY));
        } elseif (str_starts_with($subject, self::NEEDLE_GET)) {
            $subject = substr($subject, strlen(self::NEEDLE_GET));
        }

        if (str_ends_with($subject, self::NEEDLE_ACTIVITY)) {
            $subject = substr($subject, 0, -strlen(self::NEEDLE_ACTIVITY));
        }

        $subject = strtolower($subject);

        $this->path = '/' . implode('/', array_filter([
            $vendor,
            $component,
            $subject,
        ]));
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
