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

use ILIAS\Filesystem\Stream\FileStream;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\FileUpload;
use ILIAS\ResourceStorage\Manager\Manager;
use ILIAS\ResourceStorage\Revision\Revision;
use ILIAS\ResourceStorage\Policy\FileNamePolicyException;

/**
 * Class ilObjFile
 * @author  Sascha Hofmann <shofmann@databay.de>
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version $Id$
 * @ingroup ModulesFile
 */
class ilObjFile extends ilObject2 implements ilObjFileImplementationInterface
{
    use ilObjFileMetadata;
    use ilObjFileUsages;
    use ilObjFileNews;
    use ilObjFileSecureString;

    public const MODE_FILELIST = "filelist";
    public const MODE_OBJECT = "object";
    public const OBJECT_TYPE = "file";
    public const CLICK_MODE_DOWNLOAD = 1;
    public const CLICK_MODE_INFOPAGE = 2;
    protected ilObjFileInfo $file_info;

    protected ilObjFileImplementationInterface $implementation;

    protected ?string $important_info = null;
    protected int $page_count = 0;
    protected bool $rating = false;
    protected ?ilLogger $log;
    protected string $filename = '';
    protected string $filetype = '';
    protected int $filesize;
    protected int $version = 1;
    protected int $max_version = 1;
    protected ?int $copyright_id = null;
    protected string $action = '';
    protected ?string $resource_id = null;
    public string $mode = self::MODE_OBJECT;
    protected Manager $manager;
    protected FileUpload $upload;
    protected ilObjFileStakeholder $stakeholder;
    private ilDBInterface $database;
    protected int $on_click_mode = self::CLICK_MODE_DOWNLOAD;
    protected int $amount_of_downloads = 0;

    /**
     * ilObjFile constructor.
     * @param int  $a_id                ID of the object, ref_id or obj_id possible
     * @param bool $a_call_by_reference defines the $a_id a ref_id
     */
    public function __construct(int $a_id = 0, bool $a_call_by_reference = true)
    {
        global $DIC;
        $this->manager = $DIC->resourceStorage()->manage();
        $this->database = $DIC->database();
        $this->implementation = new ilObjFileImplementationEmpty();
        $this->stakeholder = new ilObjFileStakeholder($DIC->user()->getId());
        $this->upload = $DIC->upload();
        $this->version = 0;
        $this->max_version = 0;
        $this->log = ilLoggerFactory::getLogger(self::OBJECT_TYPE);

        parent::__construct($a_id, $a_call_by_reference);
        $this->initFileInfo($a_id, $a_call_by_reference);
    }

    protected function initFileInfo(int $id, bool $is_ref_id): void
    {
        $repository = new ilObjFileInfoRepository();
        if ($is_ref_id) {
            $this->file_info = $repository->getByRefId($id);
        } else {
            $this->file_info = $repository->getByObjectId($id);
        }
    }

    public function getPresentationTitle(): string
    {
        return $this->file_info->getHeaderTitle();
    }

    protected function initImplementation(): void
    {
        if ($this->resource_id && ($id = $this->manager->find(
            $this->resource_id
        )) instanceof \ILIAS\ResourceStorage\Identification\ResourceIdentification) {
            $resource = $this->manager->getResource($id);
            $this->implementation = new ilObjFileImplementationStorage($resource);
            $this->max_version = $resource->getMaxRevision(false);
            $this->version = $resource->getCurrentRevision()->getVersionNumber();
        }
        $this->initFileInfo($this->getId(), false);
    }

    public function updateObjectFromCurrentRevision(): void
    {
        $this->updateObjectFromRevision(
            $this->manager->getCurrentRevision($this->manager->find($this->getResourceId()))
        );
    }

    private function updateObjectFromRevision(Revision $r): void
    {
        $this->initFileInfo($this->getId(), false);
        $this->setTitle(
            $this->ensureSuffix(
                $r->getTitle(),
                $this->extractSuffixFromFilename($r->getInformation()->getTitle())
            )
        );
        $this->setFileName($r->getInformation()->getTitle());
        $this->update();
    }

    public function appendSuffixToTitle(string $title, string $filename): string
    {
        $suffix = $this->file_info->getSuffix();
        $filename_suffix = $this->extractSuffixFromFilename($filename);
        if (empty($suffix) || $suffix !== $filename_suffix) {
            $suffix = $filename_suffix;
        }

        $title = $this->ensureSuffix($title, $suffix);

        return $title;
    }

    /**
     * @throws FileNamePolicyException
     */
    public function appendStream(FileStream $stream, string $title): int
    {
        $title = $this->ensureSuffix($title, pathinfo($stream->getMetadata('uri'))['extension'] ?? null);
        if ($this->getResourceId() && $i = $this->manager->find($this->getResourceId())) {
            $revision = $this->manager->appendNewRevisionFromStream($i, $stream, $this->stakeholder, $title);
        } else {
            $i = $this->manager->stream($stream, $this->stakeholder, $title);
            $revision = $this->manager->getCurrentRevision($i);
            $this->setResourceId($i->serialize());
            $this->initImplementation();
        }
        $this->enableNotification();
        $this->updateObjectFromRevision($revision);

        return $revision->getVersionNumber();
    }

    /**
     * @throws FileNamePolicyException
     */
    public function appendUpload(UploadResult $result, string $title): int
    {
        $title = $this->appendSuffixToTitle($title, $result->getName());
        if ($this->getResourceId() && $i = $this->manager->find($this->getResourceId())) {
            $revision = $this->manager->appendNewRevision($i, $result, $this->stakeholder, $title);
        } else {
            $i = $this->manager->upload($result, $this->stakeholder, $title);
            $revision = $this->manager->getCurrentRevision($i);
            $this->setResourceId($i->serialize());
            $this->initImplementation();
        }
        if ($result->getMetaData()->has(ilCountPDFPagesPreProcessors::PAGE_COUNT)) {
            $this->setPageCount((int) $result->getMetaData()->get(ilCountPDFPagesPreProcessors::PAGE_COUNT));
        }
        $this->enableNotification();
        $this->updateObjectFromRevision($revision);

        return $revision->getVersionNumber();
    }

    /**
     * @throws FileNamePolicyException
     */
    public function replaceWithStream(FileStream $stream, string $title): int
    {
        if ($this->getResourceId() && $i = $this->manager->find($this->getResourceId())) {
            $revision = $this->manager->replaceWithStream($i, $stream, $this->stakeholder, $title);
        } else {
            throw new LogicException('only files with existing resource and revision can be replaced');
        }
        $this->enableNotification();
        $this->updateObjectFromRevision($revision);

        return $revision->getVersionNumber();
    }

    /**
     * @throws FileNamePolicyException
     */
    public function replaceWithUpload(UploadResult $result, string $title): int
    {
        $title = $this->appendSuffixToTitle($title, $result->getName());
        if ($this->getResourceId() && $i = $this->manager->find($this->getResourceId())) {
            $revision = $this->manager->replaceWithUpload($i, $result, $this->stakeholder, $title);
        } else {
            throw new LogicException('only files with existing resource and revision can be replaced');
        }
        if ($result->getMetaData()->has(ilCountPDFPagesPreProcessors::PAGE_COUNT)) {
            $this->setPageCount((int) $result->getMetaData()->get(ilCountPDFPagesPreProcessors::PAGE_COUNT));
        }
        $this->enableNotification();
        $this->updateObjectFromRevision($revision);

        return $revision->getVersionNumber();
    }

    /**
     * @deprecated
     */
    public function getFile(?int $a_hist_entry_id = null): string
    {
        $this->initImplementation();
        return $this->implementation->getFile($a_hist_entry_id);
    }

    public function getDirectory($a_version = 0): string
    {
        return $this->implementation->getDirectory($a_version);
    }

    public function getDownloadFilename(): string
    {
        return $this->implementation->getDownloadFilename();
    }

    public function getImportantInfo(): ?string
    {
        return $this->important_info;
    }

    public function setImportantInfo(string $a_important_info): void
    {
        $this->important_info = empty($a_important_info) ? null : $a_important_info;
    }

    public function getVersion(bool $inclduing_drafts = false): int
    {
        return $this->implementation->getVersion($inclduing_drafts);
    }

    public function setVersion(int $a_version): void
    {
        $this->version = $a_version;
    }

    public function getFileName(): string
    {
        return $this->implementation->getFileName();
    }

    public function setFileName(string $a_name): void
    {
        $this->filename = $a_name;
    }

    public function setRating(bool $a_value): void
    {
        $this->rating = $a_value;
    }

    public function setResourceId(?string $resource_id): self
    {
        $this->resource_id = $resource_id;
        return $this;
    }

    public function getResourceId(): string
    {
        return $this->resource_id ?? '-';
    }

    public function getStorageID(): ?string
    {
        return $this->implementation->getStorageID();
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @param string $a_mode self::MODE_FILELIST or self::MODE_OBJECT
     */
    public function setMode(string $a_mode): void
    {
        $this->mode = $a_mode;
    }

    public function getFileSize(): int
    {
        return $this->implementation->getFileSize();
    }

    /**
     * @return never
     */
    public function setFileSize(int $a_size): void
    {
        throw new LogicException('cannot change filesize');
    }

    public function getFileType(): string
    {
        return $this->implementation->getFileType();
    }

    /**
     * @return never
     */
    public function setFileType(string $a_type): void
    {
        throw new LogicException('cannot change filetype');
    }

    public function hasRating(): bool
    {
        return $this->rating;
    }

    public function getMaxVersion(): int
    {
        return $this->max_version;
    }

    /**
     * @return never
     */
    public function setMaxVersion(int $a_max_version): void
    {
        throw new LogicException('cannot change max-version');
    }

    public function getCopyrightID(): ?int
    {
        return $this->copyright_id;
    }

    public function setCopyrightID(?int $copyright_id): void
    {
        $this->copyright_id = $copyright_id;
    }

    public function getPageCount(): int
    {
        return $this->page_count;
    }

    public function setPageCount(int $page_count): void
    {
        $this->page_count = $page_count;
    }

    /**
     * @deprecated
     */
    public function getAction(): string
    {
        return $this->action;
    }

    public function directDownload(): bool
    {
        return $this->on_click_mode === self::CLICK_MODE_DOWNLOAD;
    }

    public function getOnClickMode(): int
    {
        return $this->on_click_mode;
    }

    public function setOnclickMode(int $on_click_mode): void
    {
        $this->on_click_mode = $on_click_mode;
    }

    public function getAmountOfDownloads(): int
    {
        return $this->amount_of_downloads;
    }

    public function setAmountOfDownloads(int $amount): void
    {
        if (0 > $amount) {
            throw new LogicException("Amount cannot be a negative number.");
        }

        $this->amount_of_downloads = $amount;
    }

    public function getLPMode(): int
    {
        return ilObjectLP::getInstance($this->getId())->getCurrentMode();
    }

    /**
     * @param $a_action
     * @return never
     * @deprecated
     */
    public function setAction(string $a_action): void
    {
        throw new LogicException('cannot change action');
    }

    public function handleChangedObjectTitle(string $new_title): void
    {
        $new_title = $this->ensureSuffix($new_title, $this->file_info->getSuffix());
        $this->setTitle($new_title);
        $this->implementation->handleChangedObjectTitle($new_title);
    }

    protected function doCreate(bool $clone_mode = false): void
    {
        $this->createProperties(true);
        $this->updateCopyright();
        $this->getObjectProperties()->storePropertyIsOnline(new ilObjectPropertyIsOnline(true));
        $this->notifyCreation($this->getId(), $this->getDescription());
    }

    protected function doRead(): void
    {
        $q = "SELECT * FROM file_data WHERE file_id = %s";
        $r = $this->database->queryF($q, ['integer'], [$this->getId()]);
        $row = $r->fetchObject();

        $this->filename = $this->secure($row->file_name ?? '');
        $this->filetype = $row->file_type ?? '';
        $this->filesize = $row->file_size ?? 0;
        $this->version = $row->version ?? 1;
        $this->max_version = $row->max_version ?? 1;
        $this->mode = $row->f_mode ?? self::MODE_OBJECT;
        $this->important_info = $row->important_info ?? "";
        $this->rating = (bool) ($row->rating ?? false);
        $this->page_count = (int) ($row->page_count ?? 0);
        $this->resource_id = $row->rid ?? null;
        $this->on_click_mode = (int) ($row->on_click_mode ?? self::CLICK_MODE_DOWNLOAD);
        $this->amount_of_downloads = (int) ($row->downloads ?? 0);

        $this->initImplementation();
    }

    protected function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = 0): void
    {
        assert($new_obj instanceof ilObjFile);
        $identification = $this->manager->find($this->resource_id);
        if ($identification === null) {
            throw new RuntimeException('Cannot clone file since no corresponding resource identification was found');
        }

        $this->cloneMetaData($new_obj);
        // object created now copy other settings
        $new_obj->updateFileData();

        // Copy Resource
        $cloned_title = $new_obj->getTitle();
        $new_resource_identification = $this->manager->clone($identification);
        $new_current_revision = $this->manager->getCurrentRevision($new_resource_identification);
        $new_obj->setResourceId($new_resource_identification->serialize());
        $new_obj->initImplementation();
        $new_obj->updateObjectFromRevision($new_current_revision); // Previews are already copied in 453
        $new_obj->setTitle($cloned_title); // see https://mantis.ilias.de/view.php?id=31375
        $new_obj->setPageCount($this->getPageCount());
        $new_obj->update();

        $new_obj->getObjectProperties()->storePropertyIsOnline(new ilObjectPropertyIsOnline(true));

        // Copy learning progress settings
        $obj_settings = new ilLPObjSettings($this->getId());
        $obj_settings->cloneSettings($new_obj->getId());
        unset($obj_settings);
    }

    protected function doUpdate(): void
    {
        $a_columns = $this->getArrayForDatabase();
        $this->database->update('file_data', $a_columns, [
            'file_id' => [
                'integer',
                $this->getId(),
            ],
        ]);

        $this->notifyUpdate($this->getId(), $this->getDescription());
        $this->initImplementation();
    }

    protected function beforeUpdate(): bool
    {
        $this->setTitle($this->ensureSuffix($this->getTitle(), $this->file_info->getSuffix()));

        // no meta data handling for file list files
        if ($this->getMode() !== self::MODE_FILELIST) {
            $this->updateMetaData();
            $this->updateCopyright();
        }

        return true;
    }

    protected function beforeDelete(): bool
    {
        // check, if file is used somewhere
        $usages = $this->getUsages();
        return count($usages) === 0;
    }

    protected function doDelete(): void
    {
        // delete file data entry
        $this->database->manipulateF("DELETE FROM file_data WHERE file_id = %s", ['integer'], [$this->getId()]);

        // delete history entries
        ilHistory::_removeEntriesForObject($this->getId());

        // delete meta data
        if ($this->getMode() != self::MODE_FILELIST) {
            $this->deleteMetaData();
        }

        // delete resource
        $identification = $this->getResourceId();
        if ($identification && $identification != '-') {
            $resource = $this->manager->find($identification);
            if ($resource !== null) {
                $this->manager->remove($resource, $this->stakeholder);
            }
        }
    }

    /**
     * @return array{file_id: int[]|string[], file_name: string[], f_mode: string[], important_info: string[]|null[], page_count: int[]|string[], rating: bool[]|string[], rid: string[], on_click_mode: int[]|string[], downloads: int[]|string[]}
     */
    private function getArrayForDatabase(): array
    {
        return [
            'file_id' => ['integer', $this->getId()],
            'file_name' => ['text', $this->getFileName()],
            'f_mode' => ['text', $this->getMode()],
            'important_info' => ['text', $this->getImportantInfo()],
            'page_count' => ['text', $this->getPageCount()],
            'rating' => ['integer', $this->hasRating()],
            'rid' => ['text', $this->resource_id ?? ''],
            'on_click_mode' => ['integer', $this->getOnClickMode()],
            'downloads' => ['integer', $this->getAmountOfDownloads()],
        ];
    }

    protected function initType(): void
    {
        $this->type = self::OBJECT_TYPE;
    }

    // Upload Handling

    /**
     * @return null
     */
    public function replaceFile($a_upload_file, $a_filename)
    {
        return null;
    }

    private function prepareUpload(): void
    {
        if (!$this->upload->hasBeenProcessed()) {
            if (defined('PATH_TO_GHOSTSCRIPT') && PATH_TO_GHOSTSCRIPT !== "") {
                $this->upload->register(new ilCountPDFPagesPreProcessors());
            }

            $this->upload->process();
        }
    }

    /**
     * @description This Method is used to append a fileupload by it's POST-name to the current ilObjFile
     * @deprecated
     * @see         appendUpload(), appendStream()
     */
    public function getUploadFile($a_upload_file, string $title, bool $a_prevent_preview = false): bool
    {
        $this->prepareUpload();

        $results = $this->upload->getResults();
        $upload = $results[$a_upload_file];

        $this->appendUpload($upload, $title);

        return true;
    }

    /**
     * @deprecated
     */
    public function isHidden(): bool
    {
        return ilObjFileAccess::_isFileHidden($this->getTitle());
    }

    /**
     * @ineritdoc
     * @deprecated
     */
    public function clearDataDirectory(): void
    {
        $this->implementation->clearDataDirectory();
    }

    /**
     * @ineritdoc
     * @deprecated
     */
    public function deleteVersions($a_hist_entry_ids = null): void
    {
        $this->implementation->deleteVersions($a_hist_entry_ids);
        // update file object as the deletion of versions might affect its attributes (title, max_version etc.)
        if ($this->getResourceId() && $rid = $this->manager->find($this->getResourceId())) {
            $latest_revision = $this->manager->getCurrentRevision($rid);
            $this->updateObjectFromRevision($latest_revision);
        }
    }

    public function sendFile(?int $a_hist_entry_id = null, bool $inline = true): void
    {
        // increment file download count by one.
        $this->setAmountOfDownloads($this->getAmountOfDownloads() + 1);
        $this->update();
        $info = (new ilObjFileInfoRepository())->getByObjectId($this->getId());
        $this->implementation->sendFile($a_hist_entry_id, $info->shouldDeliverInline());
    }

    /**
     * @deprecated no longer available since it's unclear/unspecified how to export
     */
    public function export(string $a_target_dir): void
    {
        //
    }

    /**
     * @param null $version_ids
     * @return array|ilObjFileVersion[]
     */
    public function getVersions($version_ids = null): array
    {
        return $this->implementation->getVersions($version_ids);
    }

    /**
     * Makes the specified version the current one
     * @param int $version_id The id of the version to make the current one.
     */
    public function rollback(int $version_id): void
    {
        if ($this->getResourceId() && $i = $this->manager->find($this->getResourceId())) {
            $this->manager->rollbackRevision($i, $version_id);
            $latest_revision = $this->manager->getCurrentRevision($i);
            $this->updateObjectFromRevision($latest_revision);
        } else {
            throw new LogicException('only files with existing resource and revision can be replaced');
        }
    }

    /**
     * @deprecated
     */
    public function checkFileExtension(string $new_filename, string $new_title): string
    {
        return $this->appendSuffixToTitle($new_title, $new_filename);
    }

    public function getFileExtension(): string
    {
        return $this->implementation->getFileExtension();
    }

    public function stripTitleOfFileExtension(string $a_title): string
    {
        return $this->secure(preg_replace('/\.[^.]*$/', '', $a_title));
    }
}
