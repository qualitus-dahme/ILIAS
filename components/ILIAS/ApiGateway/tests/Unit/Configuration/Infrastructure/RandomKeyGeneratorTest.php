<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Infrastructure;

use ILIAS\ApiGateway\Configuration\Infrastructure\RandomKeyGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

#[CoversClass(RandomKeyGenerator::class)]
final class RandomKeyGeneratorTest extends TestCase
{
    public function testGeneratesRandomKeyWithDefaultLength(): void
    {
        $defaultLength = 64;

        $key = RandomKeyGenerator::generate();

        self::assertSame($defaultLength, strlen($key));
        self::assertMatchesRegularExpression('/^[0-9a-f]*$/', $key);
    }

    #[DataProvider('customLengthDataProvider')]
    public function testGeneratesRandomKeyWithCustomLength(int $length): void
    {
        $key = RandomKeyGenerator::generate($length);

        self::assertSame($length, strlen($key));
        self::assertMatchesRegularExpression('/^[0-9a-f]*$/', $key);
    }

    #[DataProvider('invalidLengthDataProvider')]
    public function testGeneratesEmptyStringForInvalidLength(int $length): void
    {
        $key = RandomKeyGenerator::generate($length);

        self::assertEmpty($key);
    }

    /**
     * @return array<string, int[]>
     */
    public static function customLengthDataProvider(): array
    {
        return [
            'even length' => [32],
            'odd length' => [33],
            'length of 1' => [1],
            'large length' => [128],
        ];
    }

    /**
     * @return array<string, int[]>
     */
    public static function invalidLengthDataProvider(): array
    {
        return [
            'zero length' => [0],
            'negative length' => [-10],
        ];
    }
}
