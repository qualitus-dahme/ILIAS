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

use ILIAS\HTTP\GlobalHttpState;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Mail\Provider\MailGlobalScreenToolProvider;

/**
 * @author       Jens Conze
 * @defgroup     ServicesMail Services/Mail
 * @ingroup      ServicesMail
 * @ilCtrl_Calls ilMailGUI: ilMailFolderGUI, ilMailFormGUI, ilContactGUI, ilMailOptionsGUI, ilMailAttachmentGUI, ilMailSearchGUI, ilObjUserGUI
 */
class ilMailGUI implements ilCtrlBaseClassInterface
{
    private readonly ilGlobalTemplateInterface $tpl;
    private readonly ilCtrlInterface $ctrl;
    private readonly ilLanguage $lng;
    private string $forwardClass = '';
    private readonly GlobalHttpState $http;
    private readonly Refinery $refinery;
    private int $currentFolderId = 0;
    private readonly ilObjUser $user;
    public ilMail $umail;
    public ilMailbox $mbox;

    public function __construct()
    {
        global $DIC;
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();

        $this->lng->loadLanguageModule('mail');

        $this->mbox = new ilMailbox($this->user->getId());
        $this->umail = new ilMail($this->user->getId());
        if (
            !$DIC->rbac()->system()->checkAccess(
                'internal_mail',
                $this->umail->getMailObjectReferenceId()
            )
        ) {
            $DIC['ilErr']->raiseError($this->lng->txt('permission_denied'), $DIC['ilErr']->WARNING);
        }

        $this->initFolder();

        $toolContext = $DIC->globalScreen()
                           ->tool()
                           ->context()
                           ->current();

        $additionalDataExists = $toolContext->getAdditionalData()->exists(
            MailGlobalScreenToolProvider::SHOW_MAIL_FOLDERS_TOOL
        );
        if (false === $additionalDataExists) {
            $toolContext->addAdditionalData(MailGlobalScreenToolProvider::SHOW_MAIL_FOLDERS_TOOL, true);
        }
    }

    protected function initFolder(): void
    {
        if ($this->http->wrapper()->post()->has('mobj_id')) {
            $folderId = $this->http->wrapper()->post()->retrieve('mobj_id', $this->refinery->kindlyTo()->int());
        } elseif ($this->http->wrapper()->query()->has('mobj_id')) {
            $folderId = $this->http->wrapper()->query()->retrieve('mobj_id', $this->refinery->kindlyTo()->int());
        } else {
            $folderId = $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always($this->currentFolderId),
            ])->transform(ilSession::get('mobj_id'));
        }
        if (0 === $folderId || !$this->mbox->isOwnedFolder($folderId)) {
            $folderId = $this->mbox->getInboxFolder();
        }
        $this->currentFolderId = $folderId;
    }

    public function executeCommand(): void
    {
        $type = "";
        if ($this->http->wrapper()->query()->has('type')) {
            $type = $this->http->wrapper()->query()->retrieve('type', $this->refinery->kindlyTo()->string());
        }
        $mailId = 0;
        if ($this->http->wrapper()->query()->has('mail_id')) {
            $mailId = $this->http->wrapper()->query()->retrieve('mail_id', $this->refinery->kindlyTo()->int());
        }

        $this->ctrl->setParameterByClass(ilMailFormGUI::class, 'mobj_id', $this->currentFolderId);
        $this->ctrl->setParameterByClass(ilMailFolderGUI::class, 'mobj_id', $this->currentFolderId);

        if (ilMailFormGUI::MAIL_FORM_TYPE_SEARCH_RESULT === $type) {
            ilMailFormCall::storeReferer($this->http->request()->getQueryParams());
            $this->ctrl->redirectByClass(ilMailFormGUI::class, 'searchResults');
        } elseif (ilMailFormGUI::MAIL_FORM_TYPE_ATTACH === $type) {
            ilMailFormCall::storeReferer($this->http->request()->getQueryParams());
            $this->ctrl->redirectByClass(ilMailFormGUI::class, 'mailAttachment');
        } elseif (ilMailFormGUI::MAIL_FORM_TYPE_NEW === $type) {
            foreach (['to', 'cc', 'bcc'] as $reciepient_type) {
                $key = 'rcp_' . $reciepient_type;

                $recipients = '';
                if ($this->http->wrapper()->query()->has($key)) {
                    $recipients = $this->http->wrapper()->query()->retrieve(
                        $key,
                        $this->refinery->kindlyTo()->string()
                    );
                }

                ilSession::set($key, ilUtil::stripSlashes($recipients));

                if (ilSession::get($key) === '' &&
                    ($recipients = ilMailFormCall::getRecipients($reciepient_type))) {
                    ilSession::set($key, implode(',', $recipients));
                    ilMailFormCall::setRecipients([], $reciepient_type);
                }
            }
            ilMailFormCall::storeReferer($this->http->request()->getQueryParams());
            $this->ctrl->redirectByClass(ilMailFormGUI::class, 'mailUser');
        } elseif (ilMailFormGUI::MAIL_FORM_TYPE_REPLY === $type) {
            $this->ctrl->setParameterByClass(ilMailFormGUI::class, 'mail_id', $mailId);
            $this->ctrl->redirectByClass(ilMailFormGUI::class, 'replyMail');
        } elseif ('read' === $type) {
            $this->ctrl->setParameterByClass(ilMailFolderGUI::class, 'mail_id', $mailId);
            $this->ctrl->redirectByClass(ilMailFolderGUI::class, 'showMail');
        } elseif ('deliverFile' === $type) {
            $fileName = "";
            if ($this->http->wrapper()->post()->has('filename')) {
                $fileName = $this->http->wrapper()->post()->retrieve(
                    'filename',
                    $this->refinery->kindlyTo()->string()
                );
            } elseif ($this->http->wrapper()->query()->has('filename')) {
                $fileName = $this->http->wrapper()->query()->retrieve(
                    'filename',
                    $this->refinery->kindlyTo()->string()
                );
            }

            ilSession::set('filename', ilUtil::stripSlashes($fileName));
            $this->ctrl->setParameterByClass(ilMailFolderGUI::class, 'mail_id', $mailId);
            $this->ctrl->redirectByClass(ilMailFolderGUI::class, 'deliverFile');
        } elseif ('message_sent' === $type) {
            $this->tpl->setOnScreenMessage('success', $this->lng->txt('mail_message_send'), true);
            $this->ctrl->redirectByClass(ilMailFolderGUI::class);
        } elseif (ilMailFormGUI::MAIL_FORM_TYPE_ROLE === $type) {
            $roles = [];
            if ($this->http->wrapper()->post()->has('roles')) {
                $roles = $this->http->wrapper()->post()->retrieve(
                    'roles',
                    $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string())
                );
            } elseif ($this->http->wrapper()->query()->has('role')) {
                $roles = [$this->http->wrapper()->query()->retrieve('role', $this->refinery->kindlyTo()->string())];
            }

            if ($roles !== []) {
                ilSession::set('mail_roles', $roles);
            }

            ilMailFormCall::storeReferer($this->http->request()->getQueryParams());
            $this->ctrl->redirectByClass(ilMailFormGUI::class, 'mailRole');
        }

        $view = "";
        if ($this->http->wrapper()->query()->has('view')) {
            $view = $this->http->wrapper()->query()->retrieve('view', $this->refinery->kindlyTo()->string());
        }
        if ('my_courses' === $view) {
            $search_crs = "";
            if ($this->http->wrapper()->query()->has('search_crs')) {
                $search_crs = ilUtil::stripSlashes(
                    $this->http->wrapper()->query()->retrieve('search_crs', $this->refinery->kindlyTo()->string())
                );
            }
            $this->ctrl->setParameter($this, 'search_crs', $search_crs);
            $this->ctrl->redirectByClass(ilMailFormGUI::class, 'searchCoursesTo');
        }

        if ($this->http->wrapper()->query()->has('viewmode')) {
            $this->ctrl->setCmd('setViewMode');
        }

        $this->forwardClass = (string) $this->ctrl->getNextClass($this);
        $this->showHeader();

        switch (strtolower($this->forwardClass)) {
            case strtolower(ilMailFormGUI::class):
                $this->ctrl->forwardCommand(new ilMailFormGUI());
                break;

            case strtolower(ilContactGUI::class):
                $this->tpl->setTitle($this->lng->txt('mail_addressbook'));
                $this->ctrl->forwardCommand(new ilContactGUI());
                break;

            case strtolower(ilMailOptionsGUI::class):
                $this->tpl->setTitle($this->lng->txt('mail'));
                $this->ctrl->forwardCommand(new ilMailOptionsGUI());
                break;

            case strtolower(ilMailFolderGUI::class):
                $this->ctrl->forwardCommand(new ilMailFolderGUI());
                break;

            default:
                if (!($cmd = $this->ctrl->getCmd()) || !method_exists($this, $cmd)) {
                    $cmd = 'setViewMode';
                }

                $this->{$cmd}();
                break;
        }
    }

    private function setViewMode(): void
    {
        $targetClass = ilMailFolderGUI::class;
        if ($this->http->wrapper()->query()->has('target')) {
            $targetClass = $this->http->wrapper()->query()->retrieve(
                'target',
                $this->refinery->kindlyTo()->string()
            );
        }
        $type = "";
        if ($this->http->wrapper()->query()->has('type')) {
            $type = $this->http->wrapper()->query()->retrieve('type', $this->refinery->kindlyTo()->string());
        }
        $mailId = 0;
        if ($this->http->wrapper()->query()->has('mail_id')) {
            $mailId = $this->http->wrapper()->query()->retrieve('mail_id', $this->refinery->kindlyTo()->int());
        }

        $this->ctrl->setParameterByClass($targetClass, 'mobj_id', $this->currentFolderId);

        if ('redirect_to_read' === $type) {
            $this->ctrl->setParameterByClass(
                ilMailFolderGUI::class,
                'mail_id',
                $mailId
            );
            $this->ctrl->setParameterByClass(
                ilMailFolderGUI::class,
                'mobj_id',
                $this->currentFolderId
            );
            $this->ctrl->redirectByClass(ilMailFolderGUI::class, 'showMail');
        } elseif ('add_subfolder' === $type) {
            $this->ctrl->redirectByClass($targetClass, 'addSubFolder');
        } elseif ('enter_folderdata' === $type) {
            $this->ctrl->redirectByClass($targetClass, 'enterFolderData');
        } elseif ('confirmdelete_folderdata' === $type) {
            $this->ctrl->redirectByClass($targetClass, 'confirmDeleteFolder');
        } else {
            $this->ctrl->redirectByClass($targetClass);
        }
    }

    private function showHeader(): void
    {
        global $DIC;

        $DIC['ilHelp']->setScreenIdComponent("mail");

        $this->tpl->loadStandardTemplate();
        $this->tpl->setTitleIcon(ilUtil::getImagePath("standard/icon_mail.svg"));

        $this->ctrl->setParameterByClass(ilMailFolderGUI::class, 'mobj_id', $this->currentFolderId);
        $DIC->tabs()->addTarget('fold', $this->ctrl->getLinkTargetByClass(ilMailFolderGUI::class));
        $this->ctrl->clearParametersByClass(ilMailFormGUI::class);

        $this->ctrl->setParameterByClass(ilMailFormGUI::class, 'type', ilMailFormGUI::MAIL_FORM_TYPE_NEW);
        $this->ctrl->setParameterByClass(ilMailFormGUI::class, 'mobj_id', $this->currentFolderId);
        $DIC->tabs()->addTarget('compose', $this->ctrl->getLinkTargetByClass(ilMailFormGUI::class));
        $this->ctrl->clearParametersByClass(ilMailFormGUI::class);

        $this->ctrl->setParameterByClass(ilContactGUI::class, 'mobj_id', $this->currentFolderId);
        $DIC->tabs()->addTarget(
            'mail_addressbook',
            $this->ctrl->getLinkTargetByClass(ilContactGUI::class)
        );
        $this->ctrl->clearParametersByClass(ilContactGUI::class);

        if ($DIC->settings()->get('show_mail_settings', '0')) {
            $this->ctrl->setParameterByClass(
                ilMailOptionsGUI::class,
                'mobj_id',
                $this->currentFolderId
            );
            $DIC->tabs()->addTarget(
                'options',
                $this->ctrl->getLinkTargetByClass(ilMailOptionsGUI::class)
            );
            $this->ctrl->clearParametersByClass(ilMailOptionsGUI::class);
        }

        match (strtolower($this->forwardClass)) {
            strtolower(ilMailFormGUI::class) => $DIC->tabs()->setTabActive('compose'),
            strtolower(ilContactGUI::class) => $DIC->tabs()->setTabActive('mail_addressbook'),
            strtolower(ilMailOptionsGUI::class) => $DIC->tabs()->setTabActive('options'),
            default => $DIC->tabs()->setTabActive('fold'),
        };

        if ($this->http->wrapper()->query()->has('message_sent')) {
            $DIC->tabs()->setTabActive('fold');
        }
    }

    protected function toggleExplorerNodeState(): void
    {
        $exp = new ilMailExplorer($this, $this->user->getId());
        $exp->toggleExplorerNodeState();
    }
}
