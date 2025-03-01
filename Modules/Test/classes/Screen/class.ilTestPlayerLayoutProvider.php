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

use ILIAS\GlobalScreen\Scope\Layout\Factory\FooterModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\MainBarModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\MetaBarModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\TitleModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\ShortTitleModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\ViewTitleModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\LogoModification;
use ILIAS\GlobalScreen\Scope\Layout\Provider\AbstractModificationProvider;
use ILIAS\GlobalScreen\Scope\Layout\Provider\ModificationProvider;
use ILIAS\GlobalScreen\ScreenContext\Stack\CalledContexts;
use ILIAS\GlobalScreen\ScreenContext\Stack\ContextCollection;
use ILIAS\UI\Component\MainControls\MetaBar;
use ILIAS\UI\Component\MainControls\MainBar;
use ILIAS\UI\Component\MainControls\Footer;
use ILIAS\UI\Component\Image\Image;

/**
 * Class TestPlayerLayoutProvider
 *
 * @author      Björn Heyser <info@bjoernheyser.de>
 *
 * @package     Modules/Test
 */
class ilTestPlayerLayoutProvider extends AbstractModificationProvider implements ModificationProvider
{
    public const TEST_PLAYER_KIOSK_MODE_ENABLED = 'test_player_kiosk_mode_enabled';
    public const TEST_PLAYER_TITLE = 'test_player_title';
    public const TEST_PLAYER_VIEW_TITLE = 'test_player_view_title';
    public const TEST_PLAYER_SHORT_TITLE = 'test_player_instance_name';
    public const TEST_PLAYER_QUESTIONLIST = 'test_player_questionlist';

    private const MODIFICATION_PRIORITY = 5; //slightly above "low"

    public function isInterestedInContexts(): ContextCollection
    {
        return $this->context_collection->main();
    }

    protected function isKioskModeEnabled(CalledContexts $called_contexts): bool
    {
        return $called_contexts->current()->getAdditionalData()
            ->is(self::TEST_PLAYER_KIOSK_MODE_ENABLED, true);
    }

    public function getMainBarModification(CalledContexts $called_contexts): ?MainBarModification
    {
        $mainbar = $this->globalScreen()->layout()->factory()->mainbar();
        $has_question_list = $called_contexts->current()->getAdditionalData()
            ->exists(self::TEST_PLAYER_QUESTIONLIST);

        if (!$this->isKioskModeEnabled($called_contexts)) {
            return null;
        }

        $mainbar_modification = static fn(?MainBar $mainbar): ?MainBar => null;
        if ($has_question_list) {
            $f = $this->dic->ui()->factory();
            $r = $this->dic->ui()->renderer();
            $lng = $this->dic->language();
            $question_listing = $called_contexts->current()->getAdditionalData()->get(self::TEST_PLAYER_QUESTIONLIST);

            $mainbar_modification = static function (?MainBar $mainbar) use ($f, $r, $lng, $question_listing): ?MainBar {
                $mainbar = $mainbar->withClearedEntries();

                $icon = $f->symbol()->icon()->standard('tst', $lng->txt("more"));
                $tools_button = $f->button()->bulky($icon, $lng->txt("tools"), "#")
                    ->withEngagedState(true);

                $question_listing = $f->legacy($r->render($question_listing));

                $label = $lng->txt('mainbar_button_label_questionlist');
                $entry = $f->maincontrols()->slate()->legacy(
                    $label,
                    $f->symbol()->icon()->standard('tst', $label),
                    $question_listing
                );

                return $mainbar
                    ->withToolsButton($tools_button)
                    ->withAdditionalToolEntry('questionlist', $entry);
            };
        }

        return $mainbar
            ->withModification($mainbar_modification)
            ->withPriority(self::MODIFICATION_PRIORITY);
    }

    public function getMetaBarModification(CalledContexts $called_contexts): ?MetaBarModification
    {
        if (!$this->isKioskModeEnabled($called_contexts)) {
            return null;
        }

        return $this->globalScreen()->layout()->factory()->metabar()
            ->withModification(
                function (?MetaBar $current): ?MetaBar {
                    return null;
                }
            )->withPriority(self::MODIFICATION_PRIORITY);
    }

    public function getFooterModification(CalledContexts $called_contexts): ?FooterModification
    {
        if (!$this->isKioskModeEnabled($called_contexts)) {
            return null;
        }

        return $this->globalScreen()->layout()->factory()->footer()
            ->withModification(
                function (?Footer $current): ?Footer {
                    return null;
                }
            )->withPriority(self::MODIFICATION_PRIORITY);
    }

    public function getShortTitleModification(CalledContexts $called_contexts): ?ShortTitleModification
    {
        if (!$this->isKioskModeEnabled($called_contexts)) {
            return null;
        }

        $title = $called_contexts->current()->getAdditionalData()->get(self::TEST_PLAYER_SHORT_TITLE);
        if ($title === null) {
            $title = '';
        }
        return $this->globalScreen()->layout()->factory()->short_title()
            ->withModification(
                function (?string $content) use ($title): ?string {
                    return $title;
                }
            )->withPriority(self::MODIFICATION_PRIORITY);
    }

    public function getViewTitleModification(CalledContexts $called_contexts): ?ViewTitleModification
    {
        if (!$called_contexts->current()->getAdditionalData()->exists(self::TEST_PLAYER_VIEW_TITLE)) {
            return null;
        }

        $title = $called_contexts->current()->getAdditionalData()->get(self::TEST_PLAYER_VIEW_TITLE);
        if ($title === null) {
            $title = '';
        }
        return $this->globalScreen()->layout()->factory()->view_title()
            ->withModification(
                function (?string $content) use ($title): ?string {
                    return $title;
                }
            )->withPriority(self::MODIFICATION_PRIORITY);
    }

    public function getTitleModification(CalledContexts $called_contexts): ?TitleModification
    {
        if (!$called_contexts->current()->getAdditionalData()->exists(self::TEST_PLAYER_TITLE)) {
            return null;
        }

        $title = $called_contexts->current()->getAdditionalData()->get(self::TEST_PLAYER_TITLE);
        if ($title == null) {
            $title = '';
        }
        return $this->globalScreen()->layout()->factory()->view_title()
            ->withModification(
                function (?string $content) use ($title): ?string {
                    return $title;
                }
            )->withPriority(self::MODIFICATION_PRIORITY);
    }

    public function getLogoModification(CalledContexts $called_contexts): ?LogoModification
    {
        if (!$this->isKioskModeEnabled($called_contexts)) {
            return null;
        }
        return $this->globalScreen()->layout()->factory()->logo()->withModification(
            static function (?Image $logo): ?Image {
                return $logo->withAction('');
            }
        )->withPriority(self::MODIFICATION_PRIORITY);
    }
}
