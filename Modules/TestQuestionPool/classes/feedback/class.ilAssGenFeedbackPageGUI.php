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

/**
 * Generic feedback page GUI class
 *
 * @author Alex Killing <alex.killing@gmx.de>
 *
 * @ilCtrl_Calls ilAssGenFeedbackPageGUI: ilPageEditorGUI, ilEditClipboardGUI, ilMDEditorGUI
 * @ilCtrl_Calls ilAssGenFeedbackPageGUI: ilPublicUserProfileGUI, ilCommentGUI
 * @ilCtrl_Calls ilAssGenFeedbackPageGUI: ilPropertyFormGUI, ilInternalLinkGUI
 *
 * @ingroup ModulesTestQuestionPool
 */
class ilAssGenFeedbackPageGUI extends ilPageObjectGUI
{
    /**
     * Constructor
     */
    public function __construct($a_id = 0, $a_old_nr = 0)
    {
        global $DIC;
        $cmd_class = '';
        if ($DIC->http()->wrapper()->query()->has('cmdClass')) {
            $cmd_class = $DIC->http()->wrapper()->query()->retrieve(
                'cmdClass',
                $DIC->refinery()->kindlyTo()->string()
            );
        }

        parent::__construct("qfbg", $a_id, $a_old_nr);
        $this->setTemplateOutput(false);
        if (strtolower($cmd_class) === 'ilassquestionpreviewgui') {
            $this->setFileDownloadLink($this->ctrl->getLinkTargetByClass(ilObjQuestionPoolGUI::class, 'downloadFile'));
        } else {
            $this->setFileDownloadLink($this->ctrl->getLinkTargetByClass(ilObjTestGUI::class, 'downloadFile'));
        }
    }

    public function preview(): string
    {
        $page = parent::preview();
        $this->tabs_gui->activateTab("pg");
        return $page;
    }
}
