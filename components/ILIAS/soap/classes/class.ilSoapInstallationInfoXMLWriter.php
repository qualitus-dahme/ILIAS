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

class ilSoapInstallationInfoXMLWriter extends ilXmlWriter
{
    protected array $settings = [];

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function start(): void
    {
        $this->buildHeader();
        $this->buildInstallationInfo();
        $this->xmlStartTag("Clients");
    }

    public function addClient(string $client_directory): bool
    {
        return $this->buildClient($client_directory);
    }

    public function end(): void
    {
        $this->xmlEndTag("Clients");
        $this->buildFooter();
    }

    public function getXML(): string
    {
        return $this->xmlDumpMem(false);
    }

    private function buildHeader(): void
    {
        // we have to build the http path here since this request is client independent!
        $httpPath = ilSoapFunctions::buildHTTPPath();
        $this->xmlSetDtdDef("<!DOCTYPE Installation PUBLIC \"-//ILIAS//DTD InstallationInfo//EN\" \"" . $httpPath . "/components/ILIAS/Export/xml/ilias_installation_info_5_3.dtd\">");
        $this->xmlSetGenCmt("Export of ILIAS clients.");
        $this->xmlHeader();
        $this->xmlStartTag(
            "Installation",
            array(
                "version" => ILIAS_VERSION,
                "path" => $httpPath,
            )
        );
    }

    private function buildFooter(): void
    {
        $this->xmlEndTag('Installation');
    }

    private function buildClient(string $client_directory): bool
    {
        global $DIC;

        $ini_file = "./" . $client_directory . "/client.ini.php";

        // get settings from ini file

        $ilClientIniFile = new ilIniFile($ini_file);
        $ilClientIniFile->read();
        if ($ilClientIniFile->ERROR !== "") {
            return false;
        }
        $client_id = $ilClientIniFile->readVariable('client', 'name');
        if ($ilClientIniFile->variableExists('client', 'expose')) {
            $client_expose = $ilClientIniFile->readVariable('client', 'expose');
            if ($client_expose === "0") {
                return false;
            }
        }

        // build dsn of database connection and connect
        $ilDB = ilDBWrapperFactory::getWrapper(
            $ilClientIniFile->readVariable("db", "type")
        );
        $ilDB->initFromIniFile($ilClientIniFile);
        if ($ilDB->connect(true)) {
            unset($DIC['ilDB']);
            $DIC['ilDB'] = $ilDB;


            $settings = new ilSetting();
            unset($DIC["ilSetting"]);
            $DIC["ilSetting"] = $settings;

            // workaround to determine http path of client
            define("IL_INST_ID", (int) $settings->get("inst_id", '0'));

            $this->xmlStartTag(
                "Client",
                [
                    "inst_id" => $settings->get("inst_id"),
                    "id" => basename($client_directory),
                    'enabled' =>  $ilClientIniFile->readVariable("client", "access") ? "TRUE" : "FALSE",
                    "default_lang" => $ilClientIniFile->readVariable("language", "default")
                ]
            );
            $this->xmlEndTag("Client");
        }
        return true;
    }

    private function buildInstallationInfo(): void
    {
        $this->xmlStartTag("Settings");
        $this->xmlElement(
            "Setting",
            array("key" => "default_client"),
            $GLOBALS['DIC']['ilIliasIniFile']->readVariable("clients", "default")
        );
        $this->xmlEndTag("Settings");
    }
}
