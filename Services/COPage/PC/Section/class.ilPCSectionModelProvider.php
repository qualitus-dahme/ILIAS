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

use ILIAS\COPage\Dom\DomUtil;
use ILIAS\COPage\Editor\Components\PageComponentModelProvider;

class ilPCSectionModelProvider implements PageComponentModelProvider
{
    public function getModels(
        DomUtil $dom_util,
        \ilPageObject $page
    ): array {
        $models = [];

        foreach ($dom_util->path($page->getDomDoc(), "//Section") as $node) {
            $par = $node->parentNode;
            $pc_id = $par->getAttribute("PCID");

            $model = new stdClass();
            $model->protected = ($node->getAttribute("Protected") === "1");

            $models[$pc_id] = $model;
        }

        return $models;
    }
}
