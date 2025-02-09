<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\MainControls\Footer;

use ILIAS\DI\Container;

function footer(): string
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $url = $DIC->http()->request()->getUri()->__toString() . '&new_footer_ui=1';
    $page_demo = $f->link()->standard('See UI in fullscreen-mode', $url);

    return $renderer->render([
        $page_demo
    ]);
}

function pageFooterDemoFooter(): \ILIAS\UI\Component\MainControls\Footer
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $df = new \ILIAS\Data\Factory();

    $text = 'Additional info:';
    $links = [];
    $links[] = $f->link()->standard("Goto ILIAS", "http://www.ilias.de");
    $links[] = $f->link()->standard("Goto Mantis", "http://mantis.ilias.de");


    return $f->mainControls()->footer($links, $text)->withPermanentURL(
        $df->uri(
            $DIC->http()->request()->getUri()->__toString() . '&new_footer_ui=1'
        )
    );
}



global $DIC;
$request_wrapper = $DIC->http()->wrapper()->query();
$refinery = $DIC->refinery();

if ($request_wrapper->has('new_footer_ui')
    && $request_wrapper->retrieve('new_footer_ui', $refinery->kindlyTo()->int()) === 1
) {
    \ilInitialisation::initILIAS();
    echo(renderFooterInFullscreenMode($DIC));
    exit();
}

function renderFooterInFullscreenMode(Container $dic): string
{
    $f = $dic->ui()->factory();
    $renderer = $dic->ui()->renderer();
    $logo = $f->image()->responsive("templates/default/images/logo/HeaderIcon.svg", "ILIAS");
    $responsive_logo = $f->image()->responsive("templates/default/images/logo/HeaderIconResponsive.svg", "ILIAS");
    $breadcrumbs = pageFooterDemoCrumbs($f);
    $metabar = pageFooterDemoMetabar($f);
    $mainbar = pageFooterDemoMainbar($f);
    $footer = pageFooterDemoFooter();
    $tc = $dic->ui()->factory()->toast()->container();

    $content = pageFooterDemoContent($f);

    $page = $f->layout()->page()->standard(
        $content,
        $metabar,
        $mainbar,
        $breadcrumbs,
        $logo,
        $responsive_logo,
        "./templates/default/images/logo/favicon.ico",
        $tc,
        $footer,
        'UI PAGE FOOTER DEMO', //page title
        'ILIAS', //short title
        'Std. Page Footer Demo' //view title
    )
    ->withUIDemo(true);

    return $renderer->render($page);
}

function pageFooterDemoContent(\ILIAS\UI\Factory $f): array
{
    return [
        $f->panel()->standard(
            'All about the Footer',
            $f->legacy(
                "See bellow"
            )
        ),
    ];
}

function pageFooterDemoMetabar(\ILIAS\UI\Factory $f): \ILIAS\UI\Component\MainControls\MetaBar
{
    $help = $f->button()->bulky($f->symbol()->glyph()->help(), 'Help', '#');
    return $f->mainControls()->metaBar()->withAdditionalEntry('help', $help);
}

function pageFooterDemoCrumbs(\ILIAS\UI\Factory $f): \ILIAS\UI\Component\Breadcrumbs\Breadcrumbs
{
    return $f->breadcrumbs([]);
}

function pageFooterDemoMainbar(\ILIAS\UI\Factory $f): \ILIAS\UI\Component\MainControls\MainBar
{
    return $f->mainControls()->mainBar();
}
