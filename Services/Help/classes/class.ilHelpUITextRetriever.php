<?php

declare(strict_types=1);

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

use ILIAS\UI\Help;

/**
 * This describes a facility that the UI framework can use to retrieve some
 * help text.
 *
 * The general idea is, that components can be marked with help topics. During
 * rendering, the UI framework will try to find according help texts via this
 * facility. There neither needs to be a guarantee, that a text exists for a
 * certain topic, nor the guarantee that all texts that can be provided are
 * actually used.
 *
 * This will allow to move the actual retrieval of help texts out of the components
 * that use the UI framework. Also, it will allow to implement this interface with
 * different strategies. It is especially possible to implement this over the
 * current learning modul mechanism. But it will also be possible to implement
 * alternative mechanisms via plugins.
 */
class ilHelpUITextRetriever implements ILIAS\UI\HelpTextRetriever
{
    public function getHelpText(Help\Purpose $purpose, Help\Topic ...$topics): array
    {
        if ($purpose->isTooltip()) {
            return array_filter(
                array_map(
                    fn($topic) => ilHelp::getTooltipPresentationText($topic->get()),
                    $topics
                )
            );
        }

        return [];
    }
}
