<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Input\ViewControl\Sortation;

use ILIAS\Data\Order;

/**
 * ---
 * expected output: >
 *   There's a button with the sort glyph as a label.
 *   Clicking the button will open a dropdown with three entries.
 *   When you click an entry, the page will reload an the results will show the
 *   selected option.
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC['ui.factory'];
    $r = $DIC['ui.renderer'];

    //construct with labels and options
    $sortation = $f->input()->viewControl()->sortation([
        'Field 1, ascending' => new Order('field1', 'ASC'),
        'Field 1, descending' => new Order('field1', 'DESC'),
        'Field 2, descending' => new Order('field2', 'DESC'),

    ]);

    //wrap the control in a ViewControlContainer
    $vc_container = $f->input()->container()->viewControl()->standard([$sortation])
        ->withRequest($DIC->http()->request());

    return $r->render([
        $f->legacy('<pre>' . print_r($vc_container->getData(), true) . '</pre>'),
        $f->divider()->horizontal(),
        $vc_container
    ]);
}
