<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Domain\Model;

use DateTime;
use DateTimeInterface;
use ILIAS\ApiGateway\Configuration\Domain\Model\Setting;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Setting::class)]

class SettingTest extends TestCase
{
    private Setting $model;
    private string $key;
    private string $value;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = Setting::create(
            $this->key = 'foo',
            $this->value = 'bar',
        );
    }

    public function testHasAccessorToKey(): void
    {
        $actual = $this->model->getKey();

        self::assertSame(
            $this->key,
            $actual,
        );
    }

    public function testHasAccessorToValue(): void
    {
        $actual = $this->model->getValue();

        self::assertSame(
            $this->value,
            $actual,
        );
    }

    #[DataProvider('asIntValuesDataProvider')]
    public function testAsInt(mixed $value, int $expected): void
    {
        $setting = Setting::create('foo', $value);

        $actual = $setting->asInt();

        self::assertSame($expected, $actual);
    }

    #[DataProvider('asBoolValuesDataProvider')]
    public function testAsBool(mixed $value, bool $expected): void
    {
        $setting = Setting::create('foo', $value);

        $actual = $setting->asBool();

        self::assertSame($expected, $actual);
    }

    #[DataProvider('asStringValuesDataProvider')]
    public function testAsString(mixed $value, string $expected): void
    {
        $setting = Setting::create('foo', $value);

        $actual = $setting->asString();

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function asIntValuesDataProvider(): array
    {
        return [
            'integer' => [123, 123],
            'negative integer' => [-50, -50],
            'string integer' => ['456', 456],
            'negative string integer' => ['-50', -50],
            'string float' => ['78.9', 78],
            'negative string float' => ['-50.5', -50],
            'float' => [12.34, 12],
            'negative float' => [-50.5, -50],
            'boolean true' => [true, 1],
            'boolean false' => [false, 0],
            'string "true"' => ['true', 0],
            'string "false"' => ['false', 0],
            'string "1"' => ['1', 1],
            'string "0"' => ['0', 0],
            'null' => [null, 0],
            'non-numeric string' => ['abc', 0],
            'empty string' => ['', 0],
            'object' => [(object) ['a' => 1], 0],
            'array' => [['a' => 1], 0],
            'DateTime' => [new DateTime('2025-12-25T13:37:00+00:00'), 2025],
        ];
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function asBoolValuesDataProvider(): array
    {
        return [
            'boolean true' => [true, true],
            'boolean false' => [false, false],
            'integer 1' => [1, true],
            'integer 0' => [0, false],
            'any other integer' => [123, true],
            'any negative integer' => [-50, true],
            'string "1"' => ['1', true],
            'string "0"' => ['0', false],
            'string "true"' => ['true', true],
            'string "false"' => ['false', true],
            'empty string' => ['', false],
            'null' => [null, false],
            'non-empty array' => [['a'], true],
            'empty array' => [[], true],
            'object' => [new stdClass(), true],
            'DateTime' => [new DateTime(), true],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function asStringValuesDataProvider(): array
    {
        $datetime = new DateTime('2025-12-25T13:37:00+00:00');

        return [
            'string' => ['abc', 'abc'],
            'integer' => [123, '123'],
            'float' => [12.34, '12.34'],
            'boolean true' => [true, '1'],
            'boolean false' => [false, ''],
            'null' => [null, ''],
            'empty array' => [[], '[]'],
            'associative array' => [['a' => 'b'], '{"a":"b"}'],
            'object' => [(object) ['a' => 'b'], '{"a":"b"}'],
            'DateTime' => [$datetime, $datetime->format('Y-m-d\TH:i:sP')],
            'empty string' => ['', ''],
            'string with spaces' => ['  hello  ', 'hello'],
        ];
    }

    /**
     *
     * Setting::create() test cases
     *
     */

    public function testCreateWithSettingInstance(): void
    {
        $expected = Setting::create('original_key', 'original_value');

        $actual = Setting::create('new_key', $expected);

        self::assertSame('original_value', $actual->getValue());
        self::assertSame('new_key', $actual->getKey());
    }

    public function testCreateWithNullValue(): void
    {
        $actual = Setting::create('a_key', null);

        self::assertSame('', $actual->getValue());
    }

    public function testCreateWithDateTimeValue(): void
    {
        $expected = new DateTime();

        $actual = Setting::create('a_key', $expected);

        self::assertSame(
            $expected->format(DateTimeInterface::RFC3339),
            $actual->getValue(),
        );
    }

    public function testCreateWithObjectValue(): void
    {
        $object = new stdClass();
        $object->property = 'value';

        $actual = Setting::create('a_key', $object);

        self::assertSame('{"property":"value"}', $actual->getValue());
    }

    public function testCreateWithArrayValue(): void
    {
        $array = ['a' => 1, 'b' => 2];

        $actual = Setting::create('a_key', $array);

        self::assertSame('{"a":1,"b":2}', $actual->getValue());
    }

    public function testCreateWithResourceValueThrowsException(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Resource type cannot be converted to a Setting.');

        $actual = fopen('php://memory', 'r');

        Setting::create('a_key', $actual);
    }

    public function testCreateWithFloatValue(): void
    {
        $actual = Setting::create('a_key', 123.45);

        self::assertSame('123.45', $actual->getValue());
    }

    public function testCreateWithIntValue(): void
    {
        $actual = Setting::create('a_key', 678);

        self::assertSame('678', $actual->getValue());
    }

    public function testCreateWithBoolTrueValue(): void
    {
        $actual = Setting::create('a_key', true);

        self::assertSame('1', $actual->getValue());
    }

    public function testCreateWithBoolFalseValue(): void
    {
        $actual = Setting::create('a_key', false);

        self::assertSame('', $actual->getValue());
    }

    public function testCreateWithStringValueNeedsTrimming(): void
    {
        $actual = Setting::create('a_key', '  padded string  ');

        self::assertSame('padded string', $actual->getValue());
    }
}
