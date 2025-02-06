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

namespace ILIAS\GlobalScreen\ScreenContext\Stack;

use ILIAS\GlobalScreen\ScreenContext\ContextRepository;
use ILIAS\GlobalScreen\ScreenContext\ScreenContext;

/**
 * @package ILIAS\GlobalScreen\Scope\Tool\ScreenContext\Stack
 * @internal
 */
class ContextCollection
{
    /**
     * @var ScreenContext[]
     */
    protected array $stack = [];

    public function __construct(protected ContextRepository $repo)
    {
    }

    public function push(ScreenContext $context): void
    {
        $current = end($this->stack);
        if ($current instanceof ScreenContext) {
            if ($current->hasReferenceId()) {
                $reference_id = $current->getReferenceId();
                $ref_id = $reference_id->toInt();
                $context = $context->withReferenceId($reference_id);
            }
            $context = $context->withAdditionalData($current->getAdditionalData());
        }

        $this->stack[] = $context;
    }

    public function getLast(): ?ScreenContext
    {
        $last = end($this->stack);
        if ($last instanceof ScreenContext) {
            return $last;
        }
        return null;
    }

    /**
     * @return ScreenContext[]
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    public function getStackAsArray(): array
    {
        $return = [];
        foreach ($this->stack as $item) {
            $return[] = $item->getUniqueContextIdentifier();
        }

        return $return;
    }

    public function hasMatch(ContextCollection $other_collection): bool
    {
        $mapper = (static fn(ScreenContext $c): string => $c->getUniqueContextIdentifier());
        $mine = array_map($mapper, $this->getStack());
        $theirs = array_map($mapper, $other_collection->getStack());

        return (array_intersect($mine, $theirs) !== []);
    }

    public function main(): self
    {
        $context = $this->repo->main();
        $this->push($context);

        return $this;
    }

    public function desktop(): self
    {
        $this->push($this->repo->desktop());

        return $this;
    }

    public function repository(): self
    {
        $this->push($this->repo->repository());

        return $this;
    }

    public function administration(): self
    {
        $this->push($this->repo->administration());

        return $this;
    }

    public function internal(): self
    {
        $this->push($this->repo->internal());

        return $this;
    }

    public function external(): self
    {
        $this->push($this->repo->external());

        return $this;
    }

    public function lti(): self
    {
        $this->push($this->repo->lti());
        return $this;
    }
}
