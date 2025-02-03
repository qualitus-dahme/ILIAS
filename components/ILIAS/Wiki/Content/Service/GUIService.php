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

namespace ILIAS\Wiki\Content;

use ILIAS\Wiki\InternalGUIService;
use ILIAS\Wiki\InternalDomainService;
use ILIAS\Wiki\WikiGUIRequest;

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class GUIService
{
    protected WikiGUIRequest $request;
    protected InternalGUIService $gui_service;
    protected InternalDomainService $domain_service;

    public function __construct(
        InternalDomainService $domain_service,
        InternalGUIService $gui_service
    ) {
        $this->gui_service = $gui_service;
        $this->domain_service = $domain_service;
        $this->request = $this->gui_service->request();
    }

    protected function navigation(): NavigationManager
    {
        return $this->domain_service->content()->navigation(
            $this->domain_service->wiki()->object($this->request->getRefId()),
            $this->request->getWikiPageId(),
            $this->request->getPage(),
            $this->request->getTranslation()
        );
    }

    public function getCurrentPageGUI(): \ilWikiPageGUI
    {
        $nav = $this->navigation();
        return $this->gui_service->page()->getWikiPageGUI(
            $this->request->getRefId(),
            $nav->getCurrentPageId(),
            0,
            $nav->getCurrentPageLanguage()
        );
    }
}
