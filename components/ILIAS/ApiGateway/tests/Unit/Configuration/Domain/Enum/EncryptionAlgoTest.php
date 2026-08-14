<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Domain\Enum;

use ILIAS\ApiGateway\Configuration\Domain\Enum\EncryptionAlgo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptionAlgo::class)]
class EncryptionAlgoTest extends TestCase
{
    #[DataProvider('algorithmLengthProvider')]
    public function testGetMinimumLength(EncryptionAlgo $algo, int $expectedLength): void
    {
        self::assertSame($expectedLength, $algo->getKeyMinimumLength());
    }

    /**
     * @return array<string, array{EncryptionAlgo, int}>
     */
    public static function algorithmLengthProvider(): array
    {
        return [
            'HS256 should require 32 bytes' => [EncryptionAlgo::HS256, 32],
            'HS512 should require 64 bytes' => [EncryptionAlgo::HS512, 64],
        ];
    }
}
