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

/**
 * @ilCtrl_Calls ilContentPagePageGUI: ilPageEditorGUI, ilEditClipboardGUI, ilMDEditorGUI
 * @ilCtrl_Calls ilContentPagePageGUI: ilPublicUserProfileGUI, ilNoteGUI
 * @ilCtrl_Calls ilContentPagePageGUI: ilPropertyFormGUI, ilInternalLinkGUI, ilPageMultiLangGUI
 */
class ilContentPagePageGUI extends ilPageObjectGUI implements ilContentPageObjectConstants
{
    protected string $language = '-';

    public function __construct(int $a_id = 0, int $a_old_nr = 0, protected bool $isEmbeddedMode = false, string $language = '')
    {
        parent::__construct(self::OBJ_TYPE, $a_id, $a_old_nr, false, $language);
        $this->setTemplateTargetVar('ADM_CONTENT');
        $this->setTemplateOutput(false);
    }

    public function getProfileBackUrl(): string
    {
        if ($this->isEmbeddedMode) {
            return '';
        }

        return parent::getProfileBackUrl();
    }

    public function setDefaultLinkXml(): void
    {
        parent::setDefaultLinkXml();

        if ($this->isEmbeddedMode) {
            $linkXml = $this->getLinkXML();

            try {
                $linkXml = str_replace('<LinkTargets></LinkTargets>', '', $linkXml);

                $domDoc = new DOMDocument();
                $domDoc->loadXML('<?xml version="1.0" encoding="UTF-8"?>' . $linkXml);

                $xpath = new DOMXPath($domDoc);
                $links = $xpath->query('//IntLinkInfos/IntLinkInfo');

                if ($links->length > 0) {
                    foreach ($links as $link) {
                        /** @var DOMNode $link */
                        $link->attributes->getNamedItem('LinkTarget')->nodeValue = '_blank';
                    }
                }

                $linkXmlWithBlankTargets = $domDoc->saveXML();

                $this->setLinkXml(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $linkXmlWithBlankTargets));
            } catch (Throwable $e) {
                $this->log->error(sprintf(
                    'Could not manipulate page editor link XML: %s / Error Message: %s',
                    $linkXml,
                    $e->getMessage()
                ));
            }
        }
    }

    public function finishEditing(): void
    {
        $this->ctrl->redirectByClass(ilObjContentPageGUI::class, 'view');
    }

    public function getAdditionalPageActions(): array
    {
        $this->ctrl->setParameterByClass(ilObjContentPageGUI::class, self::HTTP_PARAM_PAGE_EDITOR_STYLE_CONTEXT, '1');

        $tabs = [
            $this->ui->factory()->link()->standard(
                $this->lng->txt('obj_sty'),
                $this->ctrl->getLinkTargetByClass([
                    ilRepositoryGUI::class,
                    ilObjContentPageGUI::class
                ], self::UI_CMD_STYLES_EDIT)
            )
        ];

        $this->ctrl->setParameterByClass(ilObjContentPageGUI::class, self::HTTP_PARAM_PAGE_EDITOR_STYLE_CONTEXT, null);

        return $tabs;
    }
}
