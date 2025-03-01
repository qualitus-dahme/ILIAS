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

use ILIAS\UI\Component\Table as DataTableInterface;
use ILIAS\UI\Implementation\Component\Table as DataTable;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer;
use ILIAS\UI\URLBuilder;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\Range;
use ILIAS\Data\Order;
use ILIAS\ResourceStorage\Services as IRSS;

class ilStudyProgrammeTypeDBRepository implements ilStudyProgrammeTypeRepository, DataTableInterface\DataRetrieval
{
    private const TYPE_TABLE = 'prg_type';

    private const FIELD_ID = 'id';
    private const FIELD_DEFAULT_LANG = 'default_lang';
    private const FIELD_OWNER = 'owner';
    private const FIELD_CREATE_DATE = 'create_date';
    private const FIELD_LAST_UPDATE = 'last_update';
    private const FIELD_ICON = 'icon';

    private const TYPE_TRANSLATION_TABLE = 'prg_translations';

    private const FIELD_PRG_TYPE_ID = 'prg_type_id';
    private const FIELD_LANG = 'lang';
    private const FIELD_MEMBER = 'member';
    private const FIELD_VALUE = 'value';

    private const AMD_TABLE = 'prg_type_adv_md_rec';

    private const FIELD_TYPE_ID = 'type_id';
    private const FIELD_REC_ID = 'rec_id';

    protected ?array $active_plugins = null;
    protected array $amd_records_assigned = [];
    protected static ?array $amd_records_available = null;

    public function __construct(
        protected ilDBInterface $db,
        protected ilStudyProgrammeSettingsRepository $settings_repo,
        protected ilObjUser $usr,
        protected ilLanguage $lng,
        protected ilComponentFactory $component_factory,
        protected UIFactory $ui_factory,
        protected Renderer $ui_renderer,
        protected IRSS $irss,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function createType(string $default_language): ilStudyProgrammeType
    {
        $id = $this->db->nextId(self::TYPE_TABLE);
        $now = date("Y-m-d H:i:s");
        $row = [
            self::FIELD_ID => $id,
            self::FIELD_DEFAULT_LANG => $default_language,
            self::FIELD_OWNER => $this->usr->getId(),
            self::FIELD_CREATE_DATE => $now,
            self::FIELD_LAST_UPDATE => $now,
            self::FIELD_ICON => ''
        ];
        $this->insertRowTypeDB($row);
        return $this->createTypeByRow($row);
    }

    protected function insertRowTypeDB(array $row): void
    {
        $this->db->insert(
            self::TYPE_TABLE,
            [
                self::FIELD_ID => ['interger',$row[self::FIELD_ID]]
                ,self::FIELD_DEFAULT_LANG => ['text',$row[self::FIELD_DEFAULT_LANG]]
                ,self::FIELD_OWNER => ['interger',$row[self::FIELD_OWNER]]
                ,self::FIELD_CREATE_DATE => ['text',$row[self::FIELD_CREATE_DATE]]
                ,self::FIELD_LAST_UPDATE => ['text',$row[self::FIELD_LAST_UPDATE]]
                ,self::FIELD_ICON => ['text',$row[self::FIELD_ICON]]
            ]
        );
    }

    protected function createTypeByRow(array $row): ilStudyProgrammeType
    {
        $return = new ilStudyProgrammeType(
            (int) $row[self::FIELD_ID],
            $this,
            $this->lng,
            $this->usr,
            $this->component_factory
        );
        $return->setDefaultLang($row[self::FIELD_DEFAULT_LANG]);
        $return->setOwner((int) $row[self::FIELD_OWNER]);
        $return->setCreateDate(
            DateTime::createFromFormat(
                ilStudyProgrammeType::DATE_TIME_FORMAT,
                $row[self::FIELD_CREATE_DATE]
            )
        );
        $return->setLastUpdate(
            DateTime::createFromFormat(
                ilStudyProgrammeType::DATE_TIME_FORMAT,
                $row[self::FIELD_LAST_UPDATE]
            )
        );
        $return = $return->withIconIdentifier($row[self::FIELD_ICON]);
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function createAMDRecord(): ilStudyProgrammeAdvancedMetadataRecord
    {
        $id = $this->db->nextId(self::AMD_TABLE);
        $row = [
            self::FIELD_ID => $id,
            self::FIELD_TYPE_ID => null,
            self::FIELD_REC_ID => null
        ];
        $this->insertRowAMDDB($row);
        return $this->createAMDByRow($row);
    }

    protected function insertRowAMDDB(array $row): void
    {
        $this->db->insert(
            self::AMD_TABLE,
            [
                self::FIELD_ID => ['interger',$row[self::FIELD_ID]]
                ,self::FIELD_TYPE_ID => ['integer',$row[self::FIELD_TYPE_ID]]
                ,self::FIELD_REC_ID => ['interger',$row[self::FIELD_REC_ID]]
            ]
        );
    }

    protected function createAMDByRow(array $row): ilStudyProgrammeAdvancedMetadataRecord
    {
        $return = new ilStudyProgrammeAdvancedMetadataRecord((int) $row[self::FIELD_ID]);
        $return->setTypeId((int) $row[self::FIELD_TYPE_ID]);
        $return->setRecId((int) $row[self::FIELD_REC_ID]);
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function createTypeTranslation(): ilStudyProgrammeTypeTranslation
    {
        $id = $this->db->nextId(self::TYPE_TRANSLATION_TABLE);
        $row = [
            self::FIELD_ID => $id,
            self::FIELD_PRG_TYPE_ID => null,
            self::FIELD_LANG => null,
            self::FIELD_MEMBER => null,
            self::FIELD_VALUE => null
        ];
        $this->insertRowTypeTranslationDB($row);
        return $this->createTypeTranslationByRow($row);
    }

    protected function insertRowTypeTranslationDB(array $row): void
    {
        $this->db->insert(
            self::TYPE_TRANSLATION_TABLE,
            [
                self::FIELD_ID => ['interger',$row[self::FIELD_ID]]
                ,self::FIELD_PRG_TYPE_ID => ['integer',$row[self::FIELD_PRG_TYPE_ID]]
                ,self::FIELD_LANG => ['text',$row[self::FIELD_LANG]]
                ,self::FIELD_MEMBER => ['text',$row[self::FIELD_MEMBER]]
                ,self::FIELD_VALUE => ['text',$row[self::FIELD_VALUE]]
            ]
        );
    }

    protected function createTypeTranslationByRow(array $row): ilStudyProgrammeTypeTranslation
    {
        $return = new ilStudyProgrammeTypeTranslation((int) $row[self::FIELD_ID]);
        $return->setPrgTypeId((int) $row[self::FIELD_PRG_TYPE_ID]);
        $return->setLang((string) $row[self::FIELD_LANG]);
        $return->setMember((string) $row[self::FIELD_MEMBER]);
        $return->setValue((string) $row[self::FIELD_VALUE]);
        return $return;
    }

    public function updateType(ilStudyProgrammeType $type): void
    {
        $this->updateRowTypeDB(
            [
                self::FIELD_ID => $type->getId()
                ,self::FIELD_DEFAULT_LANG => $type->getDefaultLang()
                ,self::FIELD_OWNER => $type->getOwner()
                ,self::FIELD_CREATE_DATE => $type->getCreateDate()->format(ilStudyProgrammeType::DATE_TIME_FORMAT)
                ,self::FIELD_LAST_UPDATE => $type->getLastUpdate()->format(ilStudyProgrammeType::DATE_TIME_FORMAT)
                ,self::FIELD_ICON => $type->getIconIdentifier()
            ]
        );
    }
    public function removeIconFromIrss(string $identifier): void
    {
        if($rid = $this->irss->manage()->find($identifier)) {
            $this->irss->manage()->remove($rid, new ilStudyProgrammeTypeStakeholder());
        }
    }

    protected function updateRowTypeDB(array $row): void
    {
        $q = 'UPDATE ' . self::TYPE_TABLE
            . '	SET'
            . '	' . self::FIELD_DEFAULT_LANG . ' = ' . $this->db->quote($row[self::FIELD_DEFAULT_LANG], 'text')
            . '	,' . self::FIELD_OWNER . ' = ' . $this->db->quote($row[self::FIELD_OWNER], 'integer')
            . '	,' . self::FIELD_CREATE_DATE . ' = ' . $this->db->quote($row[self::FIELD_CREATE_DATE], 'text')
            . '	,' . self::FIELD_LAST_UPDATE . ' = ' . $this->db->quote($row[self::FIELD_LAST_UPDATE], 'text')
            . '	,' . self::FIELD_ICON . ' = ' . $this->db->quote($row[self::FIELD_ICON], 'text')
            . '	WHERE ' . self::FIELD_ID . ' = ' . $this->db->quote($row[self::FIELD_ID], 'integer')
        ;
        $this->db->manipulate($q);
    }

    /**
     * @inheritdoc
     */
    public function updateAMDRecord(ilStudyProgrammeAdvancedMetadataRecord $rec): void
    {
        $this->updateRowAMDRecordDB(
            [
                self::FIELD_ID => $rec->getId()
                ,self::FIELD_REC_ID => $rec->getRecId()
                ,self::FIELD_TYPE_ID => $rec->getTypeId()
            ]
        );
    }

    protected function updateRowAMDRecordDB(array $row): void
    {
        $q = 'UPDATE ' . self::AMD_TABLE
            . '	SET'
            . '	' . self::FIELD_REC_ID . ' = ' . $this->db->quote($row[self::FIELD_REC_ID], 'integer')
            . '	,' . self::FIELD_TYPE_ID . ' = ' . $this->db->quote($row[self::FIELD_TYPE_ID], 'integer')
            . '	WHERE ' . self::FIELD_ID . ' = ' . $this->db->quote($row[self::FIELD_ID], 'integer')
        ;
        $this->db->manipulate($q);
    }

    /**
     * @inheritdoc
     */
    public function updateTypeTranslation(ilStudyProgrammeTypeTranslation $tt): void
    {
        $this->updateRowTypeTranslationDB(
            [
                self::FIELD_ID => $tt->getId()
                ,self::FIELD_PRG_TYPE_ID => $tt->getPrgTypeId()
                ,self::FIELD_LANG => $tt->getLang()
                ,self::FIELD_MEMBER => $tt->getMember()
                ,self::FIELD_VALUE => $tt->getValue()
            ]
        );
    }

    protected function updateRowTypeTranslationDB(array $row): void
    {
        $q = 'UPDATE ' . self::TYPE_TRANSLATION_TABLE
            . '	SET'
            . '	' . self::FIELD_PRG_TYPE_ID . ' = ' . $this->db->quote($row[self::FIELD_PRG_TYPE_ID], 'integer')
            . '	,' . self::FIELD_LANG . ' = ' . $this->db->quote($row[self::FIELD_LANG], 'text')
            . '	,' . self::FIELD_MEMBER . ' = ' . $this->db->quote($row[self::FIELD_MEMBER], 'text')
            . '	,' . self::FIELD_VALUE . ' = ' . $this->db->quote($row[self::FIELD_VALUE], 'text')
            . '	WHERE ' . self::FIELD_ID . ' = ' . $this->db->quote($row[self::FIELD_ID], 'integer')
        ;
        $this->db->manipulate($q);
    }

    /**
     * @inheritdoc
     */
    public function deleteType(ilStudyProgrammeType $type): void
    {
        $prg_ids = $this->getStudyProgrammeIdsByTypeId($type->getId());

        if (count($prg_ids)) {
            $titles = array();
            foreach ($prg_ids as $prg_id) {
                $container = new ilObjStudyProgramme($prg_id, false);
                $titles[] = $container->getTitle();
            }

            throw new ilStudyProgrammeTypeException(
                sprintf($this->lng->txt('prg_type_msg_unable_delete'), implode(', ', $titles))
            );
        }

        $disallowed = array();
        $titles = array();

        /** @var ilStudyProgrammeTypeHookPlugin $plugin */
        foreach ($this->getActivePlugins() as $plugin) {
            if (!$plugin->allowDelete($type->getId())) {
                $disallowed[] = $plugin;
                $titles[] = $plugin->getPluginName();
            }
        }
        if (count($disallowed)) {
            $msg = sprintf($this->lng->txt('prg_type_msg_deletion_prevented'), implode(', ', $titles));
            throw new ilStudyProgrammeTypePluginException($msg, $disallowed);
        }


        if($rid = $this->irss->manage()->find($type->getIconIdentifier())) {
            $this->irss->manage()->remove($rid, new ilStudyProgrammeTypeStakeholder());
        }
        $this->deleteAllTranslationsByTypeId($type->getId());
        $this->deleteAMDRecordsByTypeId($type->getId());
        $this->db->manipulate(
            'DELETE FROM ' . self::TYPE_TABLE . ' WHERE ' . self::FIELD_ID . ' = ' . $type->getId()
        );
        unset($this->amd_records_assigned[$type->getId()]);
    }

    protected function getActivePlugins(): Iterator
    {
        return $this->component_factory->getActivePluginsInSlot('prgtypehk');
    }

    protected function deleteAllTranslationsByTypeId(int $type_id): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TYPE_TRANSLATION_TABLE .
            ' WHERE ' . self::FIELD_PRG_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer')
        );
    }
    protected function deleteAMDRecordsByTypeId(int $type_id): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::AMD_TABLE .
            ' WHERE ' . self::FIELD_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer')
        );
    }

    /**
     * @inheritdoc
     */
    public function deleteAMDRecord(ilStudyProgrammeAdvancedMetadataRecord $rec): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::AMD_TABLE .
            ' WHERE ' . self::FIELD_ID . ' = ' . $this->db->quote($rec->getId(), 'integer')
        );
    }

    /**
     * @inheritdoc
     */
    public function deleteTypeTranslation(ilStudyProgrammeTypeTranslation $tt): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TYPE_TRANSLATION_TABLE .
            ' WHERE ' . self::FIELD_ID . ' = ' . $this->db->quote($tt->getId(), 'integer')
        );
    }

    /**
     * @inheritdoc
     */
    public function deleteTypeTranslationByTypeId(int $type_id): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TYPE_TRANSLATION_TABLE .
            ' WHERE ' . self::FIELD_REC_ID . ' = ' . $this->db->quote($type_id, 'integer')
        );
    }

    /**
     * @inheritdoc
     */
    public function getAllTypes(
        Range $range = null,
        Order $order = null
    ): array {
        $return = [];
        foreach ($this->getAllTypesRecords($range, $order) as $row) {
            $return[] = $this->createTypeByRow($row);
        }

        if($order) {
            list($order_field, $order_direction) = $order->join([], fn($ret, $key, $value) => [$key, $value]);
            usort(
                $return,
                static function ($a, $b) use ($order_field) {
                    switch ($order_field) {
                        case 'title':
                            $a_aspect = $a->getTitle();
                            $b_aspect = $b->getTitle();
                            break;
                        case 'description':
                            $a_aspect = $a->getDescription();
                            $b_aspect = $b->getDescription();
                            break;
                        case 'default_language':
                            $a_aspect = $a->getDefaultLang();
                            $b_aspect = $b->getDefaultLang();
                            break;
                    }
                    return $a_aspect <=> $b_aspect;
                }
            );
            if ($order_direction === 'DESC') {
                $return = array_reverse($return);
            }
        }
        if($range) {
            $return = array_slice($return, $range->getStart(), $range->getLength());
        }

        return $return;
    }

    protected function getAllTypesRecords(
    ): Generator {
        $q = 'SELECT'
            . '	' . self::FIELD_DEFAULT_LANG
            . '	,' . self::FIELD_OWNER
            . '	,' . self::FIELD_CREATE_DATE
            . '	,' . self::FIELD_LAST_UPDATE
            . '	,' . self::FIELD_ICON
            . '	,' . self::FIELD_ID
            . '	FROM ' . self::TYPE_TABLE;

        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            yield $rec;
        }
    }

    protected function getAllTypesRecordCount(): int
    {
        $q = 'SELECT count(*) AS cnt FROM ' . self::TYPE_TABLE;
        $res = $this->db->query($q);
        return (int) $this->db->fetchAssoc($res)['cnt'];
    }

    /**
     * @inheritdoc
     */
    public function getType(int $type_id): ilStudyProgrammeType
    {
        $q = 'SELECT'
            . '	' . self::FIELD_DEFAULT_LANG
            . '	,' . self::FIELD_OWNER
            . '	,' . self::FIELD_CREATE_DATE
            . '	,' . self::FIELD_LAST_UPDATE
            . '	,' . self::FIELD_ICON
            . '	,' . self::FIELD_ID
            . '	FROM ' . self::TYPE_TABLE
            . '	WHERE ' . self::FIELD_ID . ' = ' . $this->db->quote($type_id, 'integer');
        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            return $this->createTypeByRow($rec);
        }

        throw new LogicException("No entry found for type id: " . $type_id);
    }

    /**
     * @inheritdoc
     */
    public function getAllTypesArray(): array
    {
        $return = [];
        foreach ($this->getAllTypes() as $type) {
            $return[$type->getId()] = $type->getTitle();
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getAssignedAMDRecordsByType(int $type_id, bool $only_active = false): array
    {
        $active = ($only_active) ? 1 : 0; // Cache key
        if (
            array_key_exists($type_id, $this->amd_records_assigned) &&
            isset($this->amd_records_assigned[$type_id][$active]) &&
            is_array($this->amd_records_assigned[$type_id][$active])
        ) {
            return $this->amd_records_assigned[$type_id][$active];
        }
        $q = 'SELECT'
            . '	' . self::FIELD_REC_ID
            . '	FROM ' . self::AMD_TABLE
            . '	WHERE ' . self::FIELD_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer');
        $res = $this->db->query($q);
        $this->amd_records_assigned[$type_id][$active] = [];
        while ($rec = $this->db->fetchAssoc($res)) {
            $amd_record = new ilAdvancedMDRecord((int) $rec[self::FIELD_REC_ID]);
            if ($only_active) {
                if ($amd_record->isActive()) {
                    $this->amd_records_assigned[$type_id][1][] = $amd_record;
                }
            } else {
                $this->amd_records_assigned[$type_id][0][] = $amd_record;
            }
        }
        return $this->amd_records_assigned[$type_id][$active];
    }

    /**
     * @inheritdoc
     */
    public function getAssignedAMDRecordIdsByType(int $type_id, bool $only_active = false): array
    {
        $ids = array();
        /** @var ilAdvancedMDRecord $record */
        foreach ($this->getAssignedAMDRecordsByType($type_id, $only_active) as $record) {
            $ids[] = $record->getRecordId();
        }
        return $ids;
    }

    /**
     * @inheritdoc
     */
    public function getAllAMDRecords(): array
    {
        if (is_array(self::$amd_records_available)) {
            return self::$amd_records_available;
        }
        self::$amd_records_available = ilAdvancedMDRecord::_getActivatedRecordsByObjectType('prg', 'prg_type');
        return self::$amd_records_available;
    }

    /**
     * @inheritdoc
     */
    public function getAllAMDRecordIds(): array
    {
        $ids = array();
        /** @var ilAdvancedMDRecord $record */
        foreach ($this->getAllAMDRecords() as $record) {
            $ids[] = $record->getRecordId();
        }

        return $ids;
    }

    /**
     * @inheritdoc
     */
    public function getAMDRecordsByTypeIdAndRecordId(int $type_id, int $record_id): array
    {
        $q = 'SELECT'
            . '	' . self::FIELD_REC_ID
            . '	,' . self::FIELD_TYPE_ID
            . '	,' . self::FIELD_ID
            . '	FROM ' . self::AMD_TABLE
            . '	WHERE ' . self::FIELD_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer')
            . '		AND ' . self::FIELD_REC_ID . ' = ' . $this->db->quote($record_id, 'integer');
        $return = [];
        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            $return[] = $this->createAMDByRow($rec);
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getAMDRecordsByTypeId(int $type_id, bool $only_active = false): array
    {
        $q = 'SELECT'
            . '	' . self::FIELD_REC_ID
            . '	,' . self::FIELD_TYPE_ID
            . '	,' . self::FIELD_ID
            . '	FROM ' . self::AMD_TABLE
            . '	WHERE ' . self::FIELD_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer');
        $return = [];
        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            $return[] = $this->createAMDByRow($rec);
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getTranslationsArrayByTypeIdAndLangCode(int $type_id, string $lang_code): array
    {
        throw new LogicException("Not implemented yet.");
    }

    /**
     * @inheritdoc
     */
    public function getStudyProgrammesByTypeId(int $type_id): array
    {
        return $this->settings_repo->loadByType($type_id);
    }

    /**
     * @inheritdoc
     */
    public function getStudyProgrammeIdsByTypeId(int $type_id): array
    {
        return array_map(
            static function (ilStudyProgrammeSettings $settings): int {
                return $settings->getObjId();
            },
            $this->settings_repo->loadByType($type_id)
        );
    }

    /**
     * @inheritdoc
     */
    public function getAvailableAdvancedMDRecords(): array
    {
        $q = 'SELECT'
            . '	' . self::FIELD_REC_ID
            . '	,' . self::FIELD_TYPE_ID
            . '	,' . self::FIELD_ID
            . '	FROM ' . self::AMD_TABLE;
        $return = [];
        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            $return[] = $this->createAMDByRow($rec);
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getAvailableAdvancedMDRecordIds(): array
    {
        $q = 'SELECT ' . self::FIELD_REC_ID
            . '	FROM ' . self::AMD_TABLE;
        $return = [];
        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            $return[] = $rec[self::FIELD_REC_ID];
        }
        return $return;
    }

    public function getTranslationsByTypeAndLang(int $type_id, string $lang_code): array
    {
        $q = 'SELECT'
            . '	' . self::FIELD_MEMBER
            . '	,' . self::FIELD_VALUE
            . '	FROM ' . self::TYPE_TRANSLATION_TABLE
            . '	WHERE ' . self::FIELD_PRG_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer')
            . '		AND ' . self::FIELD_LANG . ' = ' . $this->db->quote($lang_code, 'text');
        $res = $this->db->query($q);
        $return = [];
        while ($rec = $this->db->fetchAssoc($res)) {
            $return[$rec[self::FIELD_MEMBER]] = $rec[self::FIELD_VALUE];
        }
        return $return;
    }

    public function getTranslationByTypeIdMemberLang(
        int $type_id,
        string $member,
        string $lang_code
    ): ?ilStudyProgrammeTypeTranslation {
        $q = 'SELECT'
            . '	' . self::FIELD_LANG
            . '	,' . self::FIELD_PRG_TYPE_ID
            . '	,' . self::FIELD_ID
            . '	,' . self::FIELD_MEMBER
            . '	,' . self::FIELD_VALUE
            . '	FROM ' . self::TYPE_TRANSLATION_TABLE
            . '	WHERE ' . self::FIELD_PRG_TYPE_ID . ' = ' . $this->db->quote($type_id, 'integer')
            . '		AND ' . self::FIELD_LANG . ' = ' . $this->db->quote($lang_code, 'text')
            . '		AND ' . self::FIELD_MEMBER . ' = ' . $this->db->quote($member, 'text');
        $res = $this->db->query($q);
        while ($rec = $this->db->fetchAssoc($res)) {
            return $this->createTypeTranslationByRow($rec);
        }
        return null;
    }


    public function getTable(): DataTable\Data
    {
        return $this->ui_factory->table()->data(
            $this->lng->txt('prg_subtypes'),
            $this->getColums(),
            $this
        );
    }

    protected function getColums(): array
    {
        $f = $this->ui_factory;
        return  [
            'title' => $f->table()->column()->text($this->lng->txt('title')),
            'description' => $f->table()->column()->text($this->lng->txt('description')),
            'default_language' => $f->table()->column()->status($this->lng->txt('default_language')),
            'icon' => $f->table()->column()->statusIcon($this->lng->txt('icon'))->withIsSortable(false)
        ];
    }

    public function getTotalRowCount(
        ?array $filter_data,
        ?array $additional_parameters
    ): ?int {
        return $this->getAllTypesRecordCount();
    }

    public function getRows(
        DataTableInterface\DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): \Generator {
        foreach ($this->getAllTypes($range, $order) as $idx => $type) {
            $default_language = $type->getDefaultLang();

            $icon = $this->ui_factory->symbol()->icon()->standard('prg', $this->lng->txt('prg_type'), 'small');
            if($type->getIconIdentifier() && $icon_path = $this->getIconPath($type)) {
                $icon = $this->ui_factory->symbol()->icon()->custom($icon_path, '');
            }

            yield $row_builder->buildDataRow(
                (string)$type->getId(),
                [
                    'title' => $type->getTitle($default_language),
                    'description' => $type->getDescription($default_language),
                    'default_language' => $default_language,
                    'icon' => $icon
                ]
            );
        }
    }

    public function getIconPathFS(ilStudyProgrammeType $type): ?string
    {
        $icon_id = $this->irss->manage()->find($type->getIconIdentifier());
        if($icon_id) {
            return $this->irss->consume()->stream($icon_id)->getStream()->getMetadata('uri');
        }
        return null;
    }

    public function getIconPath(ilStudyProgrammeType $type): ?string
    {
        $icon_id = $this->irss->manage()->find($type->getIconIdentifier());
        if($icon_id) {
            return $this->irss->consume()->src($icon_id)->getSrc();
        }
        return null;
    }
}
