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

namespace ILIAS\UI\Component\Input\ViewControl;

/**
 * This describes the factory for (view-)controls.
 */
interface Factory
{
    /**
     * ---
     * description:
     *   purpose: >
     *      Field Selection is used to limit a visualization of data to a choice of aspects,
     *      e.g. in picking specific columns of a table or fields of a diagram.
     *   composition: >
     *      A Field Selection uses checkboxes in a dropdown.
     *      A Standard Button is used to submit the user's choice.
     *   effect: >
     *      When operating the dropdown, the Multiselect is shown.
     *      The dropdown is being closed upon submission or by clicking outside of it.
     * ---
     * @param array<string,string> $options
     * @return \ILIAS\UI\Component\Input\ViewControl\FieldSelection
     */
    public function fieldSelection(
        array $options
    ): FieldSelection;

    /**
     * ---
     * description:
     *   purpose: >
     *      The Sortation Control enables the user to specify the order for the
     *      data displayed on the page.
     *   composition: >
     *      The Sortation Control offers a list of available option in a dropdown.
     *   effect: >
     *      Upon clicking an entry in the dropdown, the corresponding view is
     *      changed immediately and the dropdown closes.
     * ---
     * @param array<string, \ILIAS\Data\Order> $options
     * @return \ILIAS\UI\Component\Input\ViewControl\Sortation
     */
    public function sortation(
        array $options
    ): Sortation;

    /**
     * ---
     * description:
     *   purpose: >
     *      The pagination view control is used to display a section of a larger
     *      set of data.
     *      It allows the user to navigate through the pages by selecting the
     *      respective page and to change the amount of displayed entries.
     *   composition: >
     *      Section/Offset are controlled by a "previous" and "next" glyph to
     *      navigate through the pages; shy-buttons are used for the distinct
     *      selection of a page.
     *      A second dropdown is used to select the amount of shown entries.
     *      When the total amount of records is unknown, a Numeric Input
     *      is used to directly enter the offset along with a button to apply the inputs.
     *   effect: >
     *      Available ranges/pages are calculated by the given number of entries;
     *      when the number of entries is set to "unlimited" (PHP_MAX_INT),
     *      the section-control is not being displayed.
     *      When changing the amount of entries, pages are re-calculated and
     *      current offset is being set to the closest starting-point.
     *      If a previous/next chunk of data is not available, the according glyph
     *      is rendered unavailable.
     *      When there are more than a given amount of pages in total, first and last
     *      page will be available along with the pages surrounding the current one.
     * ---
     * @return \ILIAS\UI\Component\Input\ViewControl\Pagination
     */
    public function pagination(): Pagination;

    /**
    * ---
     * description:
     *   purpose: >
     *      This view control is only used for logical grouping of other view controls provided
     *      by this factory, to comply with the monoid-structure of UI Inputs.
     *   composition: >
     *      The view control must consist of 0, 1 or more view controls.
     *   effect: >
     *      Each view control will be rendered in the same order as provided.
     * ---
     * @param \ILIAS\UI\Component\Input\Container\ViewControl\ViewControlInput[] $view_controls
     * @return \ILIAS\UI\Component\Input\ViewControl\Group
     */
    public function group(array $view_controls): Group;

    /**
     * ---
     * description:
     *   purpose: >
     *      Input names are provided in relation to the amount of present inputs.
     *      In order to avoid wrong assignments of e.g. stored values to potentially
     *      changing view controls, a null view control can be used to 'block' the
     *      name. So, instead of checking for null, this may be used as a qualified
     *      View Control Input that will not produce any rendered output.
     *   composition: >
     *      This view control is not composed of anything.
     *   effect: >
     *      This view control is not visible and cannot be operated.
     * ---
     * @return \ILIAS\UI\Component\Input\ViewControl\NullControl
     */
    public function nullControl(): NullControl;


    /**
    * ---
     * description:
     *   purpose: >
     *      The mode view controls offers a mutually exclusive selection to
     *      display data according to the chosen aspect.
     *   composition: >
     *      This view control renders a stateful button for each option.
     *   effect: >
     *      When clicking a button of the control, the corresponding view is
     *      changed immediately; the clicked button is engaged while all others
     *      are not.
     * rules:
     *   usage:
     *      1: Mode view control MUST contain more than one option.
     *      2: Exactly one Button MUST always be active/engaged.
     *   accessibility:
     *      1: The HTML container enclosing the buttons of the Mode View Control MUST carry the role-attribute "group".
     *      2: The HTML container enclosing the buttons of the Mode View Control MUST set an aria-label describing the element.
     *      3: The options of the Mode View Control MUST be clearly labeled, describing what the button shows if clicked, e.g. "List View", "Month View", ...
     * ---
     * @param array<string, string> $options
     * @return \ILIAS\UI\Component\Input\ViewControl\Mode
     */
    public function mode(array $options): Mode;

}
