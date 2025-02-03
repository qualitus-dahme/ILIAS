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

use ILIAS\MyStaff\ilMyStaffAccess;
use ILIAS\MyStaff\ListCertificates\ilMStListCertificatesTableGUI;
use ILIAS\HTTP\Wrapper\WrapperFactory;

/**
 * Class ilMStListCertificatesGUI
 * @author            Martin Studer <ms@studer-raimann.ch>
 * @ilCtrl_IsCalledBy ilMStListCertificatesGUI: ilMyStaffGUI
 * @ilCtrl_Calls      ilMStListCertificatesGUI: ilFormPropertyDispatchGUI
 * @ilCtrl_Calls      ilMStListCertificatesGUI: ilUserCertificateApiGUI
 */
class ilMStListCertificatesGUI
{
    public const CMD_APPLY_FILTER = 'applyFilter';
    public const CMD_INDEX = 'index';
    public const CMD_GET_ACTIONS = "getActions";
    public const CMD_RESET_FILTER = 'resetFilter';
    protected ilTable2GUI $table;
    protected ilMyStaffAccess $access;
    private ilGlobalTemplateInterface $main_tpl;
    private ilCtrlInterface $ctrl;
    private ilLanguage $language;

    public function __construct()
    {
        global $DIC;
        $this->main_tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->language = $DIC->language();
        $this->access = ilMyStaffAccess::getInstance();
    }

    protected function checkAccessOrFail(): void
    {
        if ($this->access->hasCurrentUserAccessToCertificates()) {
            return;
        } else {
            $this->main_tpl->setOnScreenMessage('failure', $this->language->txt("permission_denied"), true);
            $this->ctrl->redirectByClass(ilDashboardGUI::class, "");
        }
    }

    final public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case strtolower(ilFormPropertyDispatchGUI::class):
                $this->checkAccessOrFail();

                $this->ctrl->setReturn($this, self::CMD_INDEX);
                $this->table = new ilMStListCertificatesTableGUI($this, self::CMD_INDEX);
                $this->table->executeCommand();
                break;
            case strtolower(ilUserCertificateApiGUI::class):
                $this->checkAccessOrFail();
                $this->ctrl->forwardCommand(new ilUserCertificateApiGUI());
                break;
            default:
                switch ($cmd) {
                    case self::CMD_RESET_FILTER:
                    case self::CMD_APPLY_FILTER:
                    case self::CMD_INDEX:
                    case self::CMD_GET_ACTIONS:
                        $this->$cmd();
                        break;
                    default:
                        $this->index();
                        break;
                }
                break;
        }
    }

    final public function index(): void
    {
        $this->listUsers();
    }

    final public function listUsers(): void
    {
        $this->checkAccessOrFail();

        $this->table = new ilMStListCertificatesTableGUI($this, self::CMD_INDEX);
        $this->main_tpl->setTitle($this->language->txt('mst_list_certificates'));
        $this->main_tpl->setTitleIcon(ilUtil::getImagePath('standard/icon_cert.svg'));
        $this->main_tpl->setContent($this->table->getHTML());
    }

    final public function applyFilter(): void
    {
        $this->table = new ilMStListCertificatesTableGUI($this, self::CMD_APPLY_FILTER);
        $this->table->writeFilterToSession();
        $this->table->resetOffset();
        $this->index();
    }

    final public function resetFilter(): void
    {
        $this->table = new ilMStListCertificatesTableGUI($this, self::CMD_RESET_FILTER);
        $this->table->resetOffset();
        $this->table->resetFilter();
        $this->index();
    }

    final public function getId(): string
    {
        $this->table = new ilMStListCertificatesTableGUI($this, self::CMD_INDEX);

        return $this->table->getId();
    }

    final public function cancel(): void
    {
        $this->ctrl->redirect($this);
    }
}
