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

namespace ILIAS\ApiGateway\Configuration\Domain\Model;

use DateTimeInterface;
use InvalidArgumentException;

readonly class Setting
{
    public static function create(string $key, mixed $value): Setting
    {
        if ($value instanceof self) {
            return new self($key, $value->getValue());
        }

        if ($value instanceof DateTimeInterface) {
            $finalValue = $value->format(DateTimeInterface::RFC3339);
        } elseif (null === $value) {
            $finalValue = '';
        } elseif (\is_scalar($value)) {
            // Explicitly handle all scalar types (int, float, string, bool).
            $finalValue = trim((string) $value);
        } elseif (\is_resource($value)) {
            throw new InvalidArgumentException('Resource type cannot be converted to a Setting.');
        } elseif (\is_array($value) || \is_object($value)) {
            $finalValue = json_encode($value, JSON_THROW_ON_ERROR);
        } else {
            // @codeCoverageIgnoreStart
            /**
             * This is only to make sure we catch edge cases. I cannot think of one for now
             */
            throw new InvalidArgumentException('Unsupported type for Setting: ' . \gettype($value));
            // @codeCoverageIgnoreEnd
        }

        return new self($key, $finalValue);
    }

    private function __construct(
        private string $key,
        private int|bool|string $value,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): int|bool|string
    {
        return $this->value;
    }

    public function asInt(): int
    {
        return (int) $this->value;
    }

    public function asBool(): bool
    {
        return (bool) $this->value;
    }

    public function asString(): string
    {
        return (string) $this->value;
    }
}
