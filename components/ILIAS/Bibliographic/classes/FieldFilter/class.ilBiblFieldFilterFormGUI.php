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

use ILIAS\components\OrgUnit\ARHelper\DIC;

/**
 * Class ilBiblFieldFilterFormGUI
 *
 * @author Benjamin Seglias   <bs@studer-raimann.ch>
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilBiblFieldFilterFormGUI extends ilPropertyFormGUI
{
    use DIC;
    public const F_FIELD_ID = "field_id";
    public const F_FILTER_TYPE = "filter_type";
    protected ?int $filter_id;

    /**
     * ilBiblFieldFilterFormGUI constructor.
     */
    public function __construct(protected \ilBiblFieldFilterGUI $parent_gui, protected \ilBiblFieldFilterInterface $filter, protected \ilBiblFactoryFacade $facade)
    {
        $this->filter_id = $this->filter->getId();

        $this->lng()->loadLanguageModule('bibl');
        $this->ctrl()->saveParameterByClass(ilBiblFieldFilterGUI::class, ilBiblFieldFilterGUI::FILTER_ID);

        parent::__construct();
        $this->initForm();
    }


    public function initForm(): void
    {
        $this->setTarget('_top');

        $available_fields_for_object = $this->facade->fieldFactory()->getAvailableFieldsForObjId($this->facade->iliasObjId());
        if (null !== $this->filter_id && $this->filter_id > 0) {
            $edited_filter = $this->facade->filterFactory()->findById($this->filter_id);
        }

        $existing_fields_of_object = $this->facade->filterFactory()->getAllForObjectId($this->facade->iliasObjId());
        $existing_field_ids = [];
        foreach ($existing_fields_of_object as $existing_field) {
            $existing_field_ids[] = $existing_field->getFieldId();
        }

        //show only the fields as options which don't have already a filter
        $options = [];
        foreach ($available_fields_for_object as $available_field) {
            if (isset($edited_filter) && $edited_filter->getFieldId() === $available_field->getId()) {
                array_unshift($options, $available_field);
                continue;
            }
            if (!in_array($available_field->getId(), $existing_field_ids, false)) {
                $options[] = $available_field;
            }
        }

        $select_options = [];
        foreach ($options as $ilBiblField) {
            $select_options[$ilBiblField->getId()] = $this->facade->translationFactory()->translate($ilBiblField);
        }

        asort($select_options);
        $si = new ilSelectInputGUI($this->lng()->txt("field"), self::F_FIELD_ID);
        $si->setInfo($this->lng()->txt("filter_field_info"));
        $si->setOptions($select_options);
        $si->setRequired(true);
        $this->addItem($si);

        $options = [
            ilBiblFieldFilterInterface::FILTER_TYPE_TEXT_INPUT => $this->lng()->txt(
                "filter_type_" . ilBiblFieldFilterInterface::FILTER_TYPE_TEXT_INPUT
            ),
            ilBiblFieldFilterInterface::FILTER_TYPE_SELECT_INPUT => $this->lng()->txt(
                "filter_type_" . ilBiblFieldFilterInterface::FILTER_TYPE_SELECT_INPUT
            ),
            ilBiblFieldFilterInterface::FILTER_TYPE_MULTI_SELECT_INPUT => $this->lng()->txt(
                "filter_type_" . ilBiblFieldFilterInterface::FILTER_TYPE_MULTI_SELECT_INPUT
            ),
        ];
        $si = new ilSelectInputGUI($this->lng()->txt("filter_type"), self::F_FILTER_TYPE);
        $si->setInfo($this->lng()->txt("filter_type_info"));
        $si->setOptions($options);
        $si->setRequired(true);
        $this->addItem($si);

        $this->setTitle($this->lng()->txt('filter_form_title'));

        $this->initButtons();

        $this->setFormAction($this->ctrl->getFormAction($this->parent_gui));
    }


    public function fillForm(): void
    {
        $array = [self::F_FIELD_ID => $this->filter->getFieldId(), self::F_FILTER_TYPE => $this->filter->getFilterType(),];
        $this->setValuesByArray($array);
    }


    protected function fillObject(): bool
    {
        if (!$this->checkInput()) {
            return false;
        }

        $this->filter->setFieldId($this->getInput(self::F_FIELD_ID));
        $this->filter->setFilterType($this->getInput(self::F_FILTER_TYPE));

        if ($this->filter->getId()) {
            $this->filter->update();
        } else {
            $this->filter->create();
        }

        return true;
    }


    public function saveObject(): bool
    {
        return $this->fillObject();
    }


    protected function initButtons(): void
    {
        if ($this->filter->getId()) {
            $this->addCommandButton(ilBiblFieldFilterGUI::CMD_UPDATE, $this->lng()->txt('save'));
            $this->addCommandButton(ilBiblFieldFilterGUI::CMD_CANCEL, $this->lng()->txt("cancel"));
        } else {
            $this->addCommandButton(ilBiblFieldFilterGUI::CMD_CREATE, $this->lng()->txt('create'));
            $this->addCommandButton(ilBiblFieldFilterGUI::CMD_CANCEL, $this->lng()->txt("cancel"));
        }
    }
}
