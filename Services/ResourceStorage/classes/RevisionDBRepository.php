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

namespace ILIAS\ResourceStorage\Revision\Repository;

use ILIAS\Filesystem\Stream\FileStream;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Resource\InfoResolver\InfoResolver;
use ILIAS\ResourceStorage\Resource\StorableResource;
use ILIAS\ResourceStorage\Revision\CloneRevision;
use ILIAS\ResourceStorage\Revision\FileRevision;
use ILIAS\ResourceStorage\Revision\FileStreamRevision;
use ILIAS\ResourceStorage\Revision\Revision;
use ILIAS\ResourceStorage\Revision\RevisionCollection;
use ILIAS\ResourceStorage\Revision\UploadedFileRevision;
use ILIAS\ResourceStorage\Revision\RevisionStatus;

/**
 * Class RevisionDBRepository
 * @author Fabian Schmid <fabian@sr.solutions.ch>
 * @internal
 */
class RevisionDBRepository implements RevisionRepository
{
    public const TABLE_NAME = 'il_resource_revision';
    public const IDENTIFICATION = 'rid';
    /**
     * @var Revision[]
     */
    protected array $cache = [];
    protected \ilDBInterface $db;

    public function __construct(\ilDBInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @return string[]
     */
    public function getNamesForLocking(): array
    {
        return [self::TABLE_NAME];
    }

    public function blankFromUpload(
        InfoResolver $info_resolver,
        StorableResource $resource,
        UploadResult $result,
        RevisionStatus $status
    ): UploadedFileRevision {
        $new_version_number = $info_resolver->getNextVersionNumber();
        $revision = new UploadedFileRevision($resource->getIdentification(), $result);
        $revision->setStorageID($resource->getStorageID());
        $revision->setVersionNumber($new_version_number);
        $revision->setStatus($status);

        return $revision;
    }

    public function blankFromStream(
        InfoResolver $info_resolver,
        StorableResource $resource,
        FileStream $stream,
        RevisionStatus $status,
        bool $keep_original = false
    ): FileStreamRevision {
        $new_version_number = $info_resolver->getNextVersionNumber();
        $revision = new FileStreamRevision($resource->getIdentification(), $stream, $keep_original);
        $revision->setStorageID($resource->getStorageID());
        $revision->setVersionNumber($new_version_number);
        $revision->setStatus($status);

        return $revision;
    }

    public function blankFromClone(
        InfoResolver $info_resolver,
        StorableResource $resource,
        FileRevision $revision_to_clone
    ): CloneRevision {
        $new_version_number = $info_resolver->getNextVersionNumber();
        $revision = new CloneRevision($resource->getIdentification(), $revision_to_clone);
        $revision->setStorageID($revision_to_clone->getStorageID());
        $revision->setVersionNumber($new_version_number);
        $revision->setStatus(RevisionStatus::PUBLISHED);

        return $revision;
    }

    public function store(Revision $revision): void
    {
        $rid = $revision->getIdentification()->serialize();
        $r = $this->db->queryF(
            "SELECT " . self::IDENTIFICATION . " FROM " . self::TABLE_NAME . " WHERE " . self::IDENTIFICATION . " = %s AND version_number = %s",
            ['text', 'integer'],
            [$rid, $revision->getVersionNumber()]
        );

        if ($r->numRows() > 0) {
            // UPDATE
            $this->db->update(
                self::TABLE_NAME,
                [
                    'available' => ['integer', true],
                    'owner_id' => ['integer', $revision->getOwnerId()],
                    'title' => ['text', $revision->getTitle()],
                    'status' => ['text', $revision->getStatus()->value],
                ],
                [
                    self::IDENTIFICATION => ['text', $rid],
                    'version_number' => ['integer', $revision->getVersionNumber()],
                ]
            );
        } else {
            // CREATE
            $this->db->insert(
                self::TABLE_NAME,
                [
                    self::IDENTIFICATION => ['text', $rid],
                    'version_number' => ['integer', $revision->getVersionNumber()],
                    'available' => ['integer', true],
                    'owner_id' => ['integer', $revision->getOwnerId()],
                    'title' => ['text', $revision->getTitle()],
                    'status' => ['text', $revision->getStatus()->value],
                ]
            );
        }
        $this->cache[$rid][$revision->getVersionNumber()] = $revision;
    }

    /**
     * @inheritDoc
     */
    public function get(StorableResource $resource): RevisionCollection
    {
        $collection = new RevisionCollection($resource->getIdentification());

        $rid = $resource->getIdentification()->serialize();
        if (isset($this->cache[$rid]) && is_array($this->cache[$rid])) {
            foreach ($this->cache[$rid] as $rev) {
                $collection->add($rev);
            }
            return $collection;
        }
        $r = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE " . self::IDENTIFICATION . " = %s",
            ['text'],
            [$rid]
        );
        while ($d = $this->db->fetchObject($r)) {
            $revision = new FileRevision(new ResourceIdentification($d->rid));
            $revision->setVersionNumber((int)$d->version_number);
            $revision->setOwnerId((int)$d->owner_id);
            $revision->setTitle((string)$d->title);
            $revision->setStatus(RevisionStatus::from((int)$d->status));
            $collection->add($revision);
            $this->cache[$d->rid][(int)$d->version_number] = $revision;
        }

        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function delete(Revision $revision): void
    {
        $rid = $revision->getIdentification()->serialize();
        $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE " . self::IDENTIFICATION . " = %s AND version_number = %s",
            ['text', 'integer'],
            [$rid, $revision->getVersionNumber()]
        );
        unset($this->cache[$rid][$revision->getVersionNumber()]);
    }

    public function preload(array $identification_strings): void
    {
        $r = $this->db->query(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE " . $this->db->in(
                self::IDENTIFICATION,
                $identification_strings,
                false,
                'text'
            )
        );
        while ($d = $this->db->fetchAssoc($r)) {
            $this->populateFromArray($d);
        }
    }

    public function populateFromArray(array $data): void
    {
        $revision = new FileRevision(new ResourceIdentification($data['rid']));
        $revision->setVersionNumber((int)$data['version_number']);
        $revision->setOwnerId((int)$data['owner_id']);
        $revision->setTitle((string)$data['revision_title']);
        $revision->setStatus(RevisionStatus::from((int)$data['status']));
        $this->cache[$data['rid']][(int)$data['version_number']] = $revision;
    }
}
