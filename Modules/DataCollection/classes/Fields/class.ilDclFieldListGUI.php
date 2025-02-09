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

class ilDclFieldListGUI
{
    protected \ILIAS\UI\Factory $ui_factory;
    protected \ILIAS\UI\Renderer $renderer;
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected ilToolbarGUI $toolbar;
    protected ilGlobalTemplateInterface $tpl;
    protected ilTabsGUI $tabs;
    protected ILIAS\HTTP\Services $http;
    protected ILIAS\Refinery\Factory $refinery;
    protected int $table_id;
    protected ilDclTableListGUI $parent_obj;
    protected int $obj_id;

    /**
     * Constructor
     */
    public function __construct(ilDclTableListGUI $a_parent_obj)
    {
        global $DIC;

        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->table_id = $this->http->wrapper()->query()->retrieve('table_id', $this->refinery->kindlyTo()->int());
        $locator = $DIC['ilLocator'];
        $this->parent_obj = $a_parent_obj;
        $this->obj_id = $a_parent_obj->getObjId();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->tabs = $DIC->tabs();
        $this->toolbar = $DIC->toolbar();
        $this->ui_factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();

        $DIC->help()->setScreenId('dcl_fields');

        $this->ctrl->saveParameterByClass(ilDclTableEditGUI::class, 'table_id');
        $locator->addItem(
            ilDclCache::getTableCache($this->table_id)->getTitle(),
            $this->ctrl->getLinkTargetByClass(ilDclTableEditGUI::class, 'edit')
        );
        $this->tpl->setLocator();

        if (!$this->checkAccess()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('permission_denied'), true);
            $this->ctrl->redirectByClass(ilDclRecordListGUI::class, 'listRecords');
        }
    }

    public function getTableId(): int
    {
        return $this->table_id;
    }

    /**
     * execute command
     */
    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd('listFields');
        $this->$cmd();
    }

    /**
     * Delete multiple fields
     */
    public function deleteFields(): void
    {
        if ($this->http->wrapper()->post()->has('dcl_field_ids')) {
            $field_ids = $this->http->wrapper()->post()->retrieve(
                'dcl_field_ids',
                $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->int())
            );
            $table = ilDclCache::getTableCache($this->table_id);
            foreach ($field_ids as $field_id) {
                $table->deleteField($field_id);
            }
        }

        $this->tpl->setOnScreenMessage('success', $this->lng->txt('dcl_msg_fields_deleted'), true);
        $this->ctrl->redirect($this, 'listFields');
    }

    /**
     * Confirm deletion of multiple fields
     */
    public function confirmDeleteFields(): void
    {
        $this->tabs->clearSubTabs();
        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setHeaderText($this->lng->txt('dcl_confirm_delete_fields'));

        $has_field_ids = $this->http->wrapper()->post()->has('dcl_field_ids');
        if (!$has_field_ids) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('dcl_delete_fields_no_selection'), true);
            $this->ctrl->redirect($this, 'listFields');
        }

        $field_ids = $this->http->wrapper()->post()->retrieve(
            'dcl_field_ids',
            $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->int())
        );
        foreach ($field_ids as $field_id) {
            /** @var ilDclBaseFieldModel $field */
            $field = ilDclCache::getFieldCache($field_id);
            $conf->addItem('dcl_field_ids[]', (string) $field_id, $field->getTitle());
        }

        $conf->setConfirm($this->lng->txt('delete'), 'deleteFields');
        $conf->setCancel($this->lng->txt('cancel'), 'listFields');
        $this->tpl->setContent($conf->getHTML());
    }

    /*
     * save
     */
    public function save(): void
    {
        $table_id = $this->http->wrapper()->query()->retrieve('table_id', $this->refinery->kindlyTo()->int());

        $table = ilDclCache::getTableCache($table_id);
        $fields = $table->getFields();

        $order = $this->http->wrapper()->post()->retrieve(
            'order',
            $this->refinery->kindlyTo()->dictOf($this->refinery->kindlyTo()->string())
        );
        asort($order);
        $val = 10;
        foreach (array_keys($order) as $field_id) {
            $order[$field_id] = $val;
            $val += 10;
        }

        $exportable = [];
        if ($this->http->wrapper()->post()->has("exportable")) {
            $exportable = $this->http->wrapper()->post()->retrieve(
                "exportable",
                $this->refinery->kindlyTo()->dictOf(
                    $this->refinery->kindlyTo()->string()
                )
            );
        }

        foreach ($fields as $field) {
            $field->setExportable(array_key_exists($field->getId(), $exportable) && $exportable[$field->getId()] === "on");
            $field->setOrder($order[$field->getId()]);
            $field->doUpdate();
        }

        $table->reloadFields();
        $this->tpl->setOnScreenMessage('success', $this->lng->txt("dcl_table_settings_saved"));
        $this->listFields();
    }

    /**
     * list fields
     */
    public function listFields(): void
    {
        //add button
        $add_new = $this->ui_factory->button()->primary(
            $this->lng->txt("dcl_add_new_field"),
            $this->ctrl->getLinkTargetByClass(ilDclFieldEditGUI::class, 'create')
        );
        $this->toolbar->addStickyItem($add_new);

        $switcher = new ilDclSwitcher($this->toolbar, $this->ui_factory, $this->ctrl, $this->lng);
        $switcher->addTableSwitcherToToolbar(
            $this->parent_obj->getDataCollectionObject()->getTables(),
            self::class,
            'listFields'
        );

        //table gui
        $list = new ilDclFieldListTableGUI($this, $this->ctrl->getCmd(), $this->table_id);
        $this->tpl->setContent($list->getHTML());
    }

    protected function checkAccess(): bool
    {
        $ref_id = $this->getDataCollectionObject()->getRefId();

        return ilObjDataCollectionAccess::hasAccessToEditTable($ref_id, $this->table_id);
    }

    public function getDataCollectionObject(): ilObjDataCollection
    {
        return $this->parent_obj->getDataCollectionObject();
    }
}
