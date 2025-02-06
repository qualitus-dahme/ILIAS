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

namespace ILIAS\components\WOPI\Embed;

use ILIAS\UI\Component\Button\Bulky;
use ILIAS\GlobalScreen\Scope\Layout\Provider\AbstractModificationProvider;
use ILIAS\GlobalScreen\ScreenContext\Stack\ContextCollection;
use ILIAS\GlobalScreen\ScreenContext\Stack\CalledContexts;
use ILIAS\GlobalScreen\Scope\Layout\Factory\MetaBarModification;
use ILIAS\UI\Component\MainControls\MetaBar;
use ILIAS\DI\Container;
use ILIAS\UI\Implementation\Component\SignalGeneratorInterface;
use ILIAS\GlobalScreen\Scope\Layout\Factory\PageBuilderModification;
use ILIAS\GlobalScreen\Scope\Layout\Provider\PagePart\PagePartProvider;
use ILIAS\UI\Component\Layout\Page\Page;
use ILIAS\GlobalScreen\Scope\Layout\Builder\StandardPageBuilder;
use ILIAS\Data\URI;
use ILIAS\UI\Component\Layout\Page\Standard;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
class EmbeddedApplicationGSProvider extends AbstractModificationProvider
{
    /**
     * @var int
     */
    private const USE_METABAR = 1;
    /**
     * @var int
     */
    private const USE_MODE_INFO = 2;
    private int $display_mode = self::USE_MODE_INFO;
    public const EMBEDDED_APPLICATION = 'embedded_application';
    private readonly SignalGeneratorInterface $signal_generator;

    public function __construct(Container $dic)
    {
        parent::__construct($dic);
        global $DIC;
        $this->signal_generator = $DIC["ui.signal_generator"];
    }

    public function isInterestedInContexts(): ContextCollection
    {
        return $this->context_collection->repository();
    }


    public function getPageBuilderDecorator(CalledContexts $screen_context_stack): ?PageBuilderModification
    {
        if ($this->display_mode !== self::USE_MODE_INFO) {
            return null;
        }
        if (!$screen_context_stack->current()->getAdditionalData()->exists(self::EMBEDDED_APPLICATION)) {
            return null;
        }
        $embedded_application = $screen_context_stack->current()->getAdditionalData()->get(
            self::EMBEDDED_APPLICATION
        );
        if (!$embedded_application instanceof EmbeddedApplication) {
            return null;
        }

        return $this->factory->page()->withHighPriority()->withModification(
            function (PagePartProvider $p) use ($embedded_application): Page {
                $uif = $this->dic->ui()->factory();
                $builder = new StandardPageBuilder();
                $page_part_provider = new EmbeddedApplicationPagePartProvider(
                    $p,
                    $embedded_application
                );

                $back_to = $this->dic->ctrl()->getLinkTargetByClass(
                    \ilWOPIEmbeddedApplicationGUI::class,
                    \ilWOPIEmbeddedApplicationGUI::CMD_RETURN
                );
                $back_to = new URI(rtrim(ILIAS_HTTP_PATH, '/') . '/' . ltrim($back_to, './'));
                /** @var Standard $page */
                $page = $builder->build($page_part_provider);
                if (!$embedded_application->isInline()) {
                    $page = $page->withModeInfo(
                        $uif->mainControls()->modeInfo(
                            $this->dic->language()->txt('close_wopi_editor'),
                            $back_to
                        )
                    );
                    $page = $page->withSystemInfos([]);
                }

                return $page;
            }
        );
    }

    public function getMetaBarModification(CalledContexts $screen_context_stack): ?MetaBarModification
    {
        if ($this->display_mode !== self::USE_METABAR) {
            return null;
        }
        if (!$screen_context_stack->current()->getAdditionalData()->exists(self::EMBEDDED_APPLICATION)) {
            return null;
        }

        $embedded_application = $screen_context_stack->current()->getAdditionalData()->get(
            self::EMBEDDED_APPLICATION
        );
        if (!$embedded_application instanceof EmbeddedApplication) {
            return null;
        }

        if ($embedded_application->isInline()) {
            return null;
        }

        $button = $this->buildCloseButton($embedded_application);

        return $this->factory->metabar()->withHighPriority()->withModification(
            fn(?MetaBar $metabar): ?Metabar => $metabar !== null
                ? $metabar->withClearedEntries()
                          ->withAdditionalEntry(
                              'close_editor',
                              $button
                          )
                : null
        );
    }

    protected function buildCloseButton(
        EmbeddedApplication $embedded_application,
    ): Bulky {
        $uif = $this->dic->ui()->factory();
        $back_target = $embedded_application->getBackTarget();
        $signal = $this->signal_generator->create();
        $signal->addOption('target_url', (string) $back_target);
        return $uif->button()->bulky(
            $uif->symbol()->glyph()->close(),
            $this->dic->language()->txt('close'),
            (string) $back_target
        )->withOnClick(
            $signal
        )->withOnLoadCode(fn($id): string => "il.WOPI.bindCloseSignal('$id', '{$signal->getId()}');");
    }
}
