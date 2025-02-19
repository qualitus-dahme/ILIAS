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
 * Class ilSoapHook
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class ilSoapHook
{
    protected ilComponentFactory $component_factory;

    public function __construct(ilComponentFactory $component_factory)
    {
        $this->component_factory = $component_factory;
    }

    /**
     * Get all registered soap methods over all SOAP plugins
     *
     * @return ilSoapMethod[]
     */
    public function getSoapMethods(): array
    {
        static $methods = null;
        if ($methods !== null) {
            return $methods;
        }
        $methods = array();
        foreach ($this->component_factory->getActivePluginsInSlot('soaphk') as $plugin) {
            foreach ($plugin->getSoapMethods() as $method) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    /**
     * Get all registered WSDL types over all SOAP plugins
     *
     * @return ilWsdlType[]
     */
    public function getWsdlTypes(): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }
        $types = array();
        foreach ($this->component_factory->getActivePluginsInSlot('soaphk') as $plugin) {
            foreach ($plugin->getWsdlTypes() as $type) {
                $types[] = $type;
            }
        }
        return $types;
    }


    /**
     * Get a registered soap method by name
     *
     * @param string $name
     * @return ilSoapMethod|null
     */
    public function getMethodByName(string $name): ?ilSoapMethod
    {
        $array = array_filter($this->getSoapMethods(), static function (ilSoapMethod $method) use ($name) {
            return ($method->getName() === $name);
        });
        return array_pop($array);
    }
}
