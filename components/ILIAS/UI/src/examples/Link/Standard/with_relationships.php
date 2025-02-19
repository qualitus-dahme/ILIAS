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

namespace ILIAS\UI\examples\Link\Standard;

use ILIAS\UI\Component\Link\Relationship;

/**
 * ---
 * description: >
 *   Example for rendering a standard link including relationships
 *
 * expected output: >
 *   ILIAS shows a link with the title "Goto ILIAS". Clicking the link opens the website www.ilias.de in the same
 *   browser window.
 * ---
 */
function with_relationships()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $link = $f->link()->standard("Goto ILIAS", "http://www.ilias.de")
        ->withAdditionalRelationshipToReferencedResource(Relationship::EXTERNAL)
        ->withAdditionalRelationshipToReferencedResource(Relationship::BOOKMARK);

    return $renderer->render($link);
}
