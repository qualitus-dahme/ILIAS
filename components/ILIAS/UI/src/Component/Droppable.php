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

namespace ILIAS\UI\Component;

/**
 * Interface Droppable
 *
 * Describes a UI component that can handle drop events from the browser.
 *
 * @author  nmaerchy <nm@studer-raimann.ch>
 * @date    05.05.17
 * @version 0.0.1
 *
 * @package ILIAS\UI\Component
 */
interface Droppable extends Triggerer
{
    /**
     * Get a component like this, triggering a signal of another component when files have been dropped.
     * Note: Any previous signals registered on drop are replaced.
     *
     * @param Signal $signal a ILIAS UI signal which is used on drop event
     * @return static
     */
    public function withOnDrop(Signal $signal);


    /**
     * Get a component like this, triggering a signal of another component when files have been dropped.
     * In contrast to withOnDrop, the signal is appended to existing signals for the click event.
     *
     * @param Signal $signal a ILIAS UI signal which is used on drop event
     * @return static
     */
    public function withAdditionalDrop(Signal $signal);
}
