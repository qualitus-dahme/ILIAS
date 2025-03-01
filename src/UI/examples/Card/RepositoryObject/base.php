<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Card\RepositoryObject;

function base()
{
    //Init Factory and Renderer
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $image = $f->image()->responsive(
        "./templates/default/images/logo/HeaderIcon.svg",
        "Thumbnail Example"
    );

    $card = $f->card()->repositoryObject("RepositoryObject Card Title", $image);

    //Render
    return $renderer->render($card);
}
