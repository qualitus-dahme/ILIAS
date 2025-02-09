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

namespace ILIAS\ResourceStorage\Consumer;

use ILIAS\ResourceStorage\Consumer\StreamAccess\StreamAccess;
use ILIAS\ResourceStorage\Resource\StorableResource;

/**
 * Class SrcConsumer
 * @package ILIAS\ResourceStorage\Consumer
 */
class SrcConsumer
{
    use GetRevisionTrait;

    protected ?int $revision_number = null;
    private SrcBuilder $src_builder;
    private StorableResource $resource;
    private StreamAccess $stream_access;

    /**
     * DownloadConsumer constructor.
     */
    public function __construct(SrcBuilder $src_builder, StorableResource $resource, StreamAccess $stream_access)
    {
        $this->src_builder = $src_builder;
        $this->resource = $resource;
        $this->stream_access = $stream_access;
    }

    public function getSrc(bool $signed = false): string
    {
        try {
            return $this->src_builder->getRevisionURL(
                $this->stream_access->populateRevision($this->getRevision()),
                $signed,
                60,
                null
            );
        } catch (\Throwable $e) {
            return '';
        }

    }

    /**
     * @inheritDoc
     */
    public function setRevisionNumber(int $revision_number): self
    {
        $this->revision_number = $revision_number;
        return $this;
    }
}
