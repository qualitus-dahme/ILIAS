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

namespace ILIAS\GlobalScreen\ScreenContext;

use ILIAS\Data\ReferenceId;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

/**
 * The Collection of all available Contexts in the System. You can use them in
 * your @see ScreenContextAwareProvider to announce you are interested in.
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 * @internal
 */
final class ContextRepository
{
    private array $contexts = [];
    /**
     * @var string
     */
    private const C_MAIN = 'main';
    /**
     * @var string
     */
    private const C_DESKTOP = 'desktop';
    /**
     * @var string
     */
    private const C_REPO = 'repo';
    /**
     * @var string
     */
    private const C_ADMINISTRATION = 'administration';
    /**
     * @var string
     */
    private const C_LTI = 'lti';

    protected WrapperFactory $wrapper;
    protected Factory $refinery;

    public function __construct()
    {
        global $DIC;
        $this->wrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
    }

    public function main(): ScreenContext
    {
        return $this->get(BasicScreenContext::class, self::C_MAIN);
    }

    public function internal(): ScreenContext
    {
        return $this->get(BasicScreenContext::class, 'internal');
    }

    public function external(): ScreenContext
    {
        return $this->get(BasicScreenContext::class, 'external');
    }

    public function desktop(): ScreenContext
    {
        return $this->get(BasicScreenContext::class, self::C_DESKTOP);
    }

    public function repository(): ScreenContext
    {
        $context = $this->get(BasicScreenContext::class, self::C_REPO);
        $ref_id = $this->wrapper->query()->has('ref_id')
            ? $this->wrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int())
            : null;
        if ($ref_id) {
            return $context->withReferenceId(new ReferenceId($ref_id));
        }

        return $context;
    }

    public function administration(): ScreenContext
    {
        return $this->get(BasicScreenContext::class, self::C_ADMINISTRATION);
    }

    public function lti(): ScreenContext
    {
        return $this->get(BasicScreenContext::class, self::C_LTI);
    }

    private function get(string $class_name, string $identifier): ScreenContext
    {
        if (!isset($this->contexts[$identifier])) {
            $this->contexts[$identifier] = new $class_name($identifier);
        }

        return $this->contexts[$identifier];
    }
}
