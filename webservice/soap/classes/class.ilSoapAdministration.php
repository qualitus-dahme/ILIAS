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
 * soap server
 * Base class for all SOAP registered methods. E.g ilSoapUserAdministration
 * @author Stefan Meyer <meyer@leifos.com>
 */

include_once './webservice/soap/lib/nusoap.php';
include_once("./Services/Authentication/classes/class.ilAuthUtils.php");        // to get auth mode constants

use ILIAS\Data\Result\Ok;
use ILIAS\Data\Result\Error;

class ilSoapAdministration
{
    public const NUSOAP = 1;
    public const PHP5 = 2;

    protected bool $soap_check = true;
    protected string $message = '';
    protected string $message_code = '';

    /**
     * Defines type of error handling (PHP5 || NUSOAP)
     */
    public int $error_method;

    public function __construct(bool $use_nusoap = true)
    {
        if (
            defined('IL_SOAPMODE') &&
            defined('IL_SOAPMODE_NUSOAP') &&
            IL_SOAPMODE == IL_SOAPMODE_NUSOAP
        ) {
            $this->error_method = self::NUSOAP;
        } else {
            $this->error_method = self::PHP5;
        }

        $this->initAuthenticationObject();
    }

    protected function checkSession(string $sid): bool
    {
        global $DIC;

        $ilUser = $DIC->user();

        [$sid, $client] = $this->explodeSid($sid);

        if ($sid === '') {
            $this->setMessage('No session id given');
            $this->setMessageCode('Client');
            return false;
        }
        if (!$client) {
            $this->setMessage('No client given');
            $this->setMessageCode('Client');
            return false;
        }

        if (!$GLOBALS['DIC']['ilAuthSession']->isAuthenticated()) {
            $this->setMessage('Session invalid');
            $this->setMessageCode('Client');
            return false;
        }

        $can = $DIC['legalDocuments']->canUseSoapApi()->applyTo(new Ok($ilUser))->except(
            fn($error) => new Error(is_string($error) ? $error : $error->getMessage())
        );
        if (!$can->isOk()) {
            $this->setMessage($can->error());
            $this->setMessageCode('Server');
            return false;
        }

        if ($this->soap_check) {
            $set = new ilSetting();
            $this->setMessage('SOAP is not enabled in ILIAS administration for this client');
            $this->setMessageCode('Server');
            return ((int) $set->get("soap_user_administration", '0')) === 1;
        }

        return true;
    }

    protected function explodeSid(string $sid): array
    {
        $exploded = explode('::', $sid);

        return is_array($exploded) ? $exploded : array('sid' => '', 'client' => '');
    }

    protected function setMessage(string $a_str): void
    {
        $this->message = $a_str;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function appendMessage(string $a_str): void
    {
        $this->message .= isset($this->message) ? ' ' : '';
        $this->message .= $a_str;
    }

    public function setMessageCode(string $a_code): void
    {
        $this->message_code = $a_code;
    }

    public function getMessageCode(): string
    {
        return $this->message_code;
    }

    protected function initAuth(string $sid): void
    {
        [$sid, $client] = $this->explodeSid($sid);

        if (session_status() === PHP_SESSION_ACTIVE && $sid === session_id()) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_id($sid);

        if (ilContext::getType() !== ilContext::CONTEXT_SOAP) {
            require_once("Services/Init/classes/class.ilInitialisation.php");
            ilInitialisation::reInitUser();
            ilUtil::setCookie(session_name(), $sid);
        }
    }

    protected function initIlias(): void
    {
        if (ilContext::getType() === ilContext::CONTEXT_SOAP) {
            try {
                require_once("Services/Init/classes/class.ilInitialisation.php");
                ilInitialisation::reinitILIAS();
            } catch (Exception $e) {
            }
        }
    }

    public function reInitUser(): void
    {
        if (ilContext::getType() === ilContext::CONTEXT_SOAP) {
            try {
                require_once("Services/Init/classes/class.ilInitialisation.php");
                ilInitialisation::reInitUser();
            } catch (Exception $e) {
            }
        }
    }

    protected function initAuthenticationObject(): void
    {
        include_once './Services/Authentication/classes/class.ilAuthFactory.php';
        ilAuthFactory::setContext(ilAuthFactory::CONTEXT_SOAP);
    }

    /**
     * @param string $a_message
     * @param string|int $a_code
     * @return soap_fault|SoapFault|null
     */
    protected function raiseError(string $a_message, $a_code)
    {
        switch ($this->error_method) {
            case self::NUSOAP:
                return new soap_fault($a_code, '', $a_message);
            case self::PHP5:
                return new SoapFault($a_code, $a_message);
        }
        return null;
    }

    public function isFault($object): bool
    {
        switch ($this->error_method) {
            case self::NUSOAP:
                return $object instanceof soap_fault;
            case self::PHP5:
                return $object instanceof SoapFault;
        }
        return true;
    }

    /**
     * check access for ref id: expected type, permission, return object instance if returnobject is true
     */
    protected function checkObjectAccess(
        int $ref_id,
        array $expected_type,
        string $permission,
        bool $returnObject = false
    ) {
        global $DIC;

        $rbacsystem = $DIC->rbac()->system();

        if (!ilObject::_exists($ref_id, true)) {
            return $this->raiseError(
                'No object for id.',
                'CLIENT_OBJECT_NOT_FOUND'
            );
        }

        if (ilObject::_isInTrash($ref_id)) {
            return $this->raiseError(
                'Object is already trashed.',
                'CLIENT_OBJECT_DELETED'
            );
        }

        $type = ilObject::_lookupType(ilObject::_lookupObjId($ref_id));
        if (!in_array($type, $expected_type, true)) {
            return $this->raiseError(
                "Wrong type $type for id. Expected: " . implode(",", $expected_type),
                'CLIENT_OBJECT_WRONG_TYPE'
            );
        }
        if (!$rbacsystem->checkAccess($permission, $ref_id, $type)) {
            return $this->raiseError(
                'Missing permission $permission for type $type.',
                'CLIENT_OBJECT_WRONG_PERMISSION'
            );
        }
        if ($returnObject) {
            try {
                return ilObjectFactory::getInstanceByRefId($ref_id);
            } catch (ilObjectNotFoundException $e) {
                return $this->raiseError('No valid ref_id given', 'Client');
            }
        }
        return $type;
    }

    public function getInstallationInfoXML(): string
    {
        $this->initIlias();
        if (!defined("ILIAS_WEB_DIR")) {
            define('ILIAS_WEB_DIR', dirname(__DIR__, 3) . "/data/");
        }

        $clientdirs = glob(ILIAS_WEB_DIR . "/*", GLOB_ONLYDIR);
        require_once("webservice/soap/classes/class.ilSoapInstallationInfoXMLWriter.php");
        $writer = new ilSoapInstallationInfoXMLWriter();
        $writer->start();
        if (is_array($clientdirs)) {
            foreach ($clientdirs as $clientdir) {
                $writer->addClient($clientdir);
            }
        }
        $writer->end();
        return $writer->getXML();
    }

    /**
     * @return soap_fault|SoapFault|string|null
     */
    public function getClientInfoXML(string $clientid)
    {
        $this->initIlias();
        if (!defined("ILIAS_WEB_DIR")) {
            define('ILIAS_WEB_DIR', dirname(__DIR__, 3) . "/data/");
        }
        $clientdir = ILIAS_WEB_DIR . "/" . $clientid;

        require_once("webservice/soap/classes/class.ilSoapInstallationInfoXMLWriter.php");
        $writer = new ilSoapInstallationInfoXMLWriter();
        $writer->start();
        if (!$writer->addClient($clientdir)) {
            return $this->raiseError(
                'Client ID ' . $clientid . 'does not exist!',
                'Client'
            );
        }
        $writer->end();
        return $writer->getXML();
    }
}
