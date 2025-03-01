<?php

declare(strict_types=1);

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

/**
 * Content styles table
 * @author Alexander Killing <killing@leifos.de>
 */
class ilContentStylesTableGUI extends ilTable2GUI
{
    protected \ILIAS\Style\Content\InternalGUIService $gui;
    protected int $default_style = 0;
    protected int $fixed_style = 0;
    protected ilSetting $settings;
    protected ilRbacSystem $rbacsystem;

    public function __construct(
        ilContentStyleSettingsGUI $a_parent_obj,
        string $a_parent_cmd,
        array $a_data
    ) {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->settings = $DIC->settings();
        $this->rbacsystem = $DIC->rbac()->system();
        $ilCtrl = $DIC->ctrl();
        $lng = $DIC->language();
        $ilSetting = $DIC->settings();
        $this->gui = $DIC->contentStyle()
            ->internal()
            ->gui();

        $this->fixed_style = (int) $ilSetting->get("fixed_content_style_id");
        $this->default_style = (int) $ilSetting->get("default_content_style_id");

        $this->setId("sty_cs");

        parent::__construct($a_parent_obj, $a_parent_cmd);
        $this->setData($a_data);
        $this->setTitle($lng->txt("content_styles"));

        $this->addColumn("", "", "1", true);
        $this->addColumn($this->lng->txt("title"));
        $this->addColumn($this->lng->txt("sty_nr_learning_modules"));
        $this->addColumn($this->lng->txt("purpose"));
        $this->addColumn($this->lng->txt("sty_scope"));
        $this->addColumn($this->lng->txt("active"));
        $this->addColumn($this->lng->txt("actions"));

        $this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
        $this->setRowTemplate("tpl.content_style_row.html", "Services/Style/Content");
        if ($this->parent_obj->checkPermission("sty_write_content", false)) {
            $this->addMultiCommand("deleteStyle", $lng->txt("delete"));
            $this->addCommandButton("saveActiveStyles", $lng->txt("sty_save_active_styles"));
        }
    }

    /**
     * Fill table row
     */
    protected function fillRow(array $a_set): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        $ui_factory = $this->gui->ui()->factory();
        $ui_renderer = $this->gui->ui()->renderer();

        if ($a_set["id"] > 0) {
            $this->tpl->setCurrentBlock("cb");
            $this->tpl->setVariable("ID", $a_set["id"]);
            $this->tpl->parseCurrentBlock();

            $this->tpl->setCurrentBlock("cb_act");
            if ($a_set["active"]) {
                $this->tpl->setVariable("ACT_CHECKED", "checked='checked'");
            }
            $this->tpl->setVariable("ID", $a_set["id"]);
            $this->tpl->parseCurrentBlock();

            $this->tpl->setCurrentBlock("edit_link");
            $ilCtrl->setParameterByClass("ilobjstylesheetgui", "obj_id", $a_set["id"]);
            $this->tpl->setVariable("EDIT_LINK", $ilCtrl->getLinkTargetByClass("ilobjstylesheetgui", ""));
            $ilCtrl->setParameterByClass("ilobjstylesheetgui", "obj_id", "");
            $this->tpl->setVariable("EDIT_TITLE", $a_set["title"]);
            $this->tpl->parseCurrentBlock();
        } else {
            $this->tpl->setVariable("TITLE", $a_set["title"]);
        }

        $ilCtrl->setParameter($this->parent_obj, "id", $a_set["id"]);
        if ($a_set["id"] > 0 && $this->parent_obj->checkPermission("sty_write_content", false)) {
            $actions = [];

            // default style
            if ($this->default_style == $a_set["id"]) {
                $actions[] = $ui_factory->link()->standard(
                    $lng->txt("sty_remove_global_default_state"),
                    $ilCtrl->getLinkTarget($this->parent_obj, "toggleGlobalDefault")
                );
            } elseif ($a_set["active"]) {
                $actions[] = $ui_factory->link()->standard(
                    $lng->txt("sty_make_global_default"),
                    $ilCtrl->getLinkTarget($this->parent_obj, "toggleGlobalDefault")
                );
            }

            // fixed style
            if ($this->fixed_style == $a_set["id"]) {
                $actions[] = $ui_factory->link()->standard(
                    $lng->txt("sty_remove_global_fixed_state"),
                    $ilCtrl->getLinkTarget($this->parent_obj, "toggleGlobalFixed")
                );
            } elseif ($a_set["active"]) {
                $actions[] = $ui_factory->link()->standard(
                    $lng->txt("sty_make_global_fixed"),
                    $ilCtrl->getLinkTarget($this->parent_obj, "toggleGlobalFixed")
                );
            }
            $actions[] = $ui_factory->link()->standard(
                $lng->txt("sty_set_scope"),
                $ilCtrl->getLinkTarget($this->parent_obj, "setScope")
            );

            $dd = $ui_factory->dropdown()->standard($actions);

            $this->tpl->setVariable("ACTIONS", $ui_renderer->render($dd));

            if ($a_set["id"] == $this->fixed_style) {
                $this->tpl->setVariable("PURPOSE", $lng->txt("global_fixed"));
            }
            if ($a_set["id"] == $this->default_style) {
                $this->tpl->setVariable("PURPOSE", $lng->txt("global_default"));
            }
        }
        $ilCtrl->setParameter($this->parent_obj, "id", "");

        $this->tpl->setVariable("NR_LM", $a_set["lm_nr"]);
        if (($a_set["category"] ?? 0) > 0) {
            $this->tpl->setVariable(
                "SCOPE",
                ilObject::_lookupTitle(
                    ilObject::_lookupObjId((int) $a_set["category"])
                )
            );
        }
    }
}
