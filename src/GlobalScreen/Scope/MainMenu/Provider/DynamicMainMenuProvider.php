<?php namespace ILIAS\GlobalScreen\Scope\MainMenu\Provider;

use ILIAS\GlobalScreen\Provider\DynamicProvider;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\Tool\Tool;
use ILIAS\GlobalScreen\Scope\Tool\Context\Provider\ContextAwareDynamicProvider;
use ILIAS\GlobalScreen\Scope\Tool\Context\Stack\ContextCollection;
use ILIAS\GlobalScreen\Scope\Tool\Context\Stack\ContextStack;

/**
 * Interface DynamicMainMenuProvider
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
interface DynamicMainMenuProvider extends ContextAwareDynamicProvider
{

    /**
     * @return ContextCollection
     */
    public function isInterestedInContexts() : ContextCollection;


    /**
     * @param ContextStack $called_contexts
     *
     * @return Tool[] These Slates
     * can be passed to the MainMenu dynamic for a specific location/context.
     * @see DynamicProvider
     *
     */
    public function getToolsForContextStack(ContextStack $called_contexts) : array;
}
