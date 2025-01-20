<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Month;

/**
 * ---
 * description: >
 *   Example for rendering a dropdown button showing the default month/year while not opened and a selection of months while
 *   opened.
 *
 * expected output: >
 *   ILIAS shows a button including a month and year. Clicking the button will open a selection of other months and years
 *   which can be selected. Another click onto a month opens a dialog which confirms the click. In this dialog the selected
 *   month (e.g. 03-2020) is included.
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    return $renderer->render($f->button()->month("02-2017")->withOnLoadCode(function ($id) {
        return "$(\"#$id\").on('il.ui.button.month.changed', function(el, id, month) { alert(\"Clicked: \" + id + ' with ' + month);});";
    }));
}
