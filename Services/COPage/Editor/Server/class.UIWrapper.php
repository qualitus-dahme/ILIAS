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

namespace ILIAS\COPage\Editor\Server;

use ILIAS\Repository\Form\FormAdapterGUI;
use ILIAS\COPage\PC\PCDefinition;

/**
 *
 * @author Alexander Killing <killing@leifos.de>
 */
class UIWrapper
{
    protected \ILIAS\COPage\PC\DomainService $pc_def;
    protected \ILIAS\DI\UIServices $ui;
    protected \ilLanguage $lng;

    public function __construct(
        \ILIAS\DI\UIServices $ui,
        \ilLanguage $lng
    ) {
        global $DIC;

        $this->pc_def = $DIC->copage()->internal()->domain()->pc();
        $this->ui = $ui;
        $this->lng = $lng;
        $this->lng->loadLanguageModule("copg");
    }

    public function getButton(
        string $content,
        string $type,
        string $action,
        array $data = null,
        string $component = "",
        bool $primary = false,
        string $aria_label = ""
    ): \ILIAS\UI\Component\Button\Button {
        $ui = $this->ui;
        $f = $ui->factory();
        if ($primary) {
            $b = $f->button()->primary($content, "");
        } else {
            $b = $f->button()->standard($content, "");
        }
        if ($data === null) {
            $data = [];
        }
        $b = $b->withOnLoadCode(
            function ($id) use ($type, $data, $action, $component, $aria_label) {
                $code = "document.querySelector('#$id').setAttribute('data-copg-ed-type', '$type');
                         document.querySelector('#$id').setAttribute('data-copg-ed-component', '$component');
                         document.querySelector('#$id').setAttribute('data-copg-ed-action', '$action'); ";
                if ($aria_label !== "") {
                    $code .= "document.querySelector('#$id').setAttribute('aria-label', '$aria_label'); ";
                }
                foreach ($data as $key => $val) {
                    $code .= "\n document.querySelector('#$id').setAttribute('data-copg-ed-par-$key', '$val');";
                }
                return $code;
            }
        );
        return $b;
    }

    public function getRenderedInfoBox(string $text, array $buttons = []): string
    {
        $ui = $this->ui;
        $f = $ui->factory();
        $m = $f->messageBox()->info($text);
        if (count($buttons)) {
            $m = $m->withButtons($buttons);
        }
        return $ui->renderer()->renderAsync($m);
    }

    public function getRenderedSuccessBox(string $text): string
    {
        $ui = $this->ui;
        $f = $ui->factory();
        $m = $f->messageBox()->success($text);
        return $ui->renderer()->renderAsync($m);
    }

    public function getRenderedFailureBox(): string
    {
        $ui = $this->ui;
        $f = $ui->factory();
        $m = $f->messageBox()->failure($this->lng->txt("copg_an_error_occured"))
            ->withLinks([$f->link()->standard($this->lng->txt("copg_details"), "#")]);

        return $ui->renderer()->renderAsync($m);
    }

    public function getRenderedButton(
        string $content,
        string $type,
        string $action,
        array $data = null,
        string $component = "",
        bool $primary = false,
        string $aria_label = ""
    ): string {
        $ui = $this->ui;
        $b = $this->getButton($content, $type, $action, $data, $component, $primary, $aria_label);
        return $ui->renderer()->renderAsync($b);
    }

    public function getRenderedModalFailureBox(): string
    {
        $ui = $this->ui;
        $f = $ui->factory();
        $m = $f->messageBox()->failure($this->lng->txt("copg_error_occured_modal"))
               ->withButtons([$f->button()->standard($this->lng->txt("copg_reload_page"), "#")->withOnLoadCode(function ($id) {
                   return
                       "$(\"#$id\").click(function() { location.reload(); return false;});";
               })]);

        return $ui->renderer()->renderAsync($m) . "<p>" . $this->lng->txt("copg_details") . ":</p>";
    }

    public function getRenderedButtonGroups(array $groups): string
    {
        $ui = $this->ui;
        $r = $ui->renderer();

        $tpl = new \ilTemplate("tpl.editor_button_group.html", true, true, "Services/COPage");

        foreach ($groups as $buttons) {
            foreach ($buttons as $action => $lng_key) {
                $tpl->setCurrentBlock("button");
                $b = $this->getButton($this->lng->txt($lng_key), "multi", $action);
                $tpl->setVariable("BUTTON", $r->renderAsync($b));
                $tpl->parseCurrentBlock();
            }
            $tpl->setCurrentBlock("section");
            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
    }

    public function getRenderedFormFooter(array $buttons): string
    {
        $ui = $this->ui;
        $r = $ui->renderer();

        $tpl = new \ilTemplate("tpl.form_footer.html", true, true, "Services/COPage");

        $html = "";
        foreach ($buttons as $b) {
            $html .= $ui->renderer()->renderAsync($b);
        }

        $tpl->setVariable("BUTTONS", $html);

        return $tpl->get();
    }

    public function getRenderedForm(
        \ilPropertyFormGUI $form,
        array $buttons
    ): string {
        $form->clearCommandButtons();
        $cnt = 0;
        foreach ($buttons as $button) {
            $cnt++;
            $form->addCommandButton("", $button[2], "cmd-" . $cnt);
        }
        $html = $form->getHTMLAsync();
        $cnt = 0;
        foreach ($buttons as $button) {
            $cnt++;
            $html = str_replace(
                "id='cmd-" . $cnt . "'",
                " data-copg-ed-type='form-button' data-copg-ed-action='" . $button[1] . "' data-copg-ed-component='" . $button[0] . "'",
                $html
            );
        }
        return $html;
    }

    public function getRenderedAdapterForm(
        FormAdapterGUI $form,
        array $buttons,
        string $id = ""
    ): string {
        $button_html = "";
        foreach ($buttons as $button) {
            $button_html .= $this->getRenderedButton(
                $button[2],
                "form-button",
                $button[1],
                null,
                $button[0]
            );
        }
        $html = $form->render();
        $tag = "button";
        $html = preg_replace("#\\<" . $tag . "(.*)/" . $tag . ">#iUs", "", $html, 1);
        $footer_pos = stripos($html, "il-standard-form-footer");

        $html =
            substr($html, 0, $footer_pos) .
            preg_replace("#\\<" . $tag . "(.*)/" . $tag . ">#iUs", $button_html, substr($html, $footer_pos), 1);

        if ($id !== "") {
            $html = str_replace("<form ", "<form id='$id' ", $html);
        }
        return $html;
    }

    /**
     * Send whole page as response
     * @param bool|array|string $updated
     * @throws \ilDateTimeException
     */
    public function sendPage(
        \ilPageObjectGUI $page_gui,
        $updated
    ): Response {
        $error = null;
        $page_data = "";
        $last_change = null;
        $pc_model = null;

        if ($updated !== true) {
            if (is_array($updated)) {
                $error = "";
                foreach ($updated as $u) {
                    if (is_array($u)) {
                        $error .= implode("<br />", $u);
                    } else {
                        $error .= "<br />" . $u;
                    }
                }
            } elseif (is_string($updated)) {
                $error = $updated;
            } else {
                $error = print_r($updated, true);
            }
        } else {
            $page_gui->setOutputMode(\ilPageObjectGUI::EDIT);
            $page_gui->setDefaultLinkXml(); // fixes #31225
            $page_gui->setTemplateOutput(false);
            $page_data = $page_gui->showPage();
            $pc_model = $page_gui->getPageObject()->getPCModel();
            $last_change = $page_gui->getPageObject()->getLastChange();
        }

        $data = new \stdClass();
        $data->renderedContent = $page_data . $this->getOnloadCode($page_gui);
        $data->pcModel = $pc_model;
        $data->error = $error;
        if ($last_change) {
            $lu = new \ilDateTime($last_change, IL_CAL_DATETIME);
            \ilDatePresentation::setUseRelativeDates(false);
            $data->last_update = \ilDatePresentation::formatDate($lu, true);
        }
        return new Response($data);
    }

    protected function getOnloadCode(\ilPageObjectGUI $page_gui): string
    {
        $page = $page_gui->getPageObject();
        $defs = $this->pc_def->definition()->getPCDefinitions();
        $all_onload_code = [];
        foreach ($defs as $def) {
            $pc_class = $def["pc_class"];
            /** @var \ilPageContent $pc_obj */
            $pc_obj = new $pc_class($page);

            // onload code
            $onload_code = $pc_obj->getOnloadCode("edit");
            foreach ($onload_code as $code) {
                $all_onload_code[] = $code;
            }
        }
        $code_str = "";
        if (count($all_onload_code) > 0) {
            $code_str = "<script>" . implode("\n", $all_onload_code) . "</script>";
        }
        return $code_str;
    }

    public function sendFormError(
        string $form
    ): Response {
        $data = new \stdClass();
        $data->formError = true;
        $data->form = $form;
        return new Response($data);
    }

    public function getRenderedViewControl(
        array $actions
    ): string {
        $ui = $this->ui;
        $cnt = 0;
        $view_modes = [];
        foreach ($actions as $act) {
            $cnt++;
            $view_modes[$act[2]] = "cmd-" . $cnt;
        }
        $vc = $ui->factory()->viewControl()->mode($view_modes, "");
        $html = $ui->renderer()->render($vc);
        $cnt = 0;
        foreach ($actions as $act) {
            $cnt++;
            $html = str_replace(
                'data-action="cmd-' . $cnt . '"',
                " data-copg-ed-type='view-control' data-copg-ed-action='" . $act[1] . "' data-copg-ed-component='" . $act[0] . "'",
                $html
            );
        }
        $html = str_replace("id=", "data-id=", $html);
        return $html;
    }


    public function getLink(
        string $content,
        string $component,
        string $type,
        string $action,
        array $data = null
    ): \ILIAS\UI\Component\Button\Shy {
        $ui = $this->ui;
        $f = $ui->factory();
        $l = $f->button()->shy($content, "");
        if ($data === null) {
            $data = [];
        }
        $l = $l->withOnLoadCode(
            function ($id) use ($component, $type, $data, $action) {
                $code = "document.querySelector('#$id').setAttribute('data-copg-ed-component', '$component');
                         document.querySelector('#$id').setAttribute('data-copg-ed-type', '$type');
                         document.querySelector('#$id').setAttribute('data-copg-ed-action', '$action')";
                foreach ($data as $key => $val) {
                    $code .= "\n document.querySelector('#$id').setAttribute('data-copg-ed-par-$key', '$val');";
                }
                return $code;
            }
        );
        return $l;
    }

    public function getRenderedLink(
        string $content,
        string $component,
        string $type,
        string $action,
        array $data = null
    ): string {
        $ui = $this->ui;
        $l = $this->getLink($content, $component, $type, $action, $data);
        return $ui->renderer()->renderAsync($l);
    }

    public function getRenderedIcon(string $type): string
    {
        $ui = $this->ui;
        $f = $ui->factory();
        $r = $ui->renderer();
        $i = $f->symbol()->icon()->standard($type, $type, 'medium');
        return $r->render($i);
    }

    public function getRenderedListingPanelTemplate(
        string $title = "",
        bool $leading_image = false
    ): string {
        $ui = $this->ui;
        $f = $ui->factory();
        $r = $ui->renderer();
        $dd = $f->dropdown()->standard([
            $f->link()->standard("#link-label#", "#")
        ]);

        $item = $f->item()->standard("#item-title#")->withActions($dd);
        if ($leading_image) {
            $item = $item->withLeadImage(
                $f->image()->responsive("#img-src#", "#img-alt#")
            );
        }
        $p = $f->panel()->listing()->standard(
            $title,
            [$f->item()->group(
                "",
                [$item]
            )]
        );

        return $r->render($p);
    }
}
