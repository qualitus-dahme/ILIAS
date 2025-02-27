<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Player\Video;

/**
 * ---
 * description: >
 *   Example for rendering the YouTube video player
 *
 * expected output: >
 *   ILIAS shows a base YouTube video player. On the left side you will see a Start/Stop
 *   symbol, followed by a time bar and on the right side a symbol for volume control and for the the full screen.
 *   A big start symbol is shown in the middle of the start screen. At the top left you will see an avatar, the video title
 *   and on the left side a symbol for sharing the video. If the video is stopped a box with the title "More Videos" and a list
 *   of videos appears in the bottommost section. The box will disappear if the video is started again.
 *
 *   In addition following functions have to be tested:
 *   - The video starts playing if clicking the start/stop symbol in the middle of the image. The video stops after another click.
 *   - The sound fades or raises if the volumes gets changed by using the volume control.
 *   - Clicking the full screen icon maximizes the video player to the size of the desktop size. Clicking ESC will diminish the video player.
 * ---
 */
function video_youtube(): string
{
    global $DIC;
    $renderer = $DIC->ui()->renderer();
    $f = $DIC->ui()->factory();

    $video = $f->player()->video("https://www.youtube.com/embed/YSN2osYbshQ");

    return $renderer->render($video);
}
