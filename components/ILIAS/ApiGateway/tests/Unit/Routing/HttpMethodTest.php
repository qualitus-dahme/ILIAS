<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use ILIAS\ApiGateway\Routing\HttpMethod;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpMethod::class)]
final class HttpMethodTest extends TestCase
{
    #[DataProvider('validTypesDataProvider')]
    public function testCreatesFromAnyType(mixed $input, HttpMethod $expected): void
    {
        self::assertEquals($expected, HttpMethod::fromAny($input));
    }

    #[DataProvider('invalidTypesDataProvider')]
    public function testThrowsExceptionForInvalidType(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpMethod::fromAny($input);
    }

    /**
     * @return array<string, array{mixed, HttpMethod}>
     */
    public static function validTypesDataProvider(): array
    {
        return [
            'GET uppercase' => ['GET', HttpMethod::GET],
            'get lowercase' => ['get', HttpMethod::GET],
            'HEAD uppercase' => ['HEAD', HttpMethod::HEAD],
            'head lowercase' => ['head', HttpMethod::HEAD],
            'POST uppercase' => ['POST', HttpMethod::POST],
            'post lowercase' => ['post', HttpMethod::POST],
            'PUT uppercase' => ['PUT', HttpMethod::PUT],
            'put lowercase' => ['put', HttpMethod::PUT],
            'PATCH uppercase' => ['PATCH', HttpMethod::PATCH],
            'patch lowercase' => ['patch', HttpMethod::PATCH],
            'DELETE uppercase' => ['DELETE', HttpMethod::DELETE],
            'delete lowercase' => ['delete', HttpMethod::DELETE],
            'stringable object GET' => [new StringableObject('GET'), HttpMethod::GET],
            'stringable object HEAD' => [new StringableObject('HEAD'), HttpMethod::HEAD],
            'stringable object post' => [new StringableObject('post'), HttpMethod::POST],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invalidTypesDataProvider(): array
    {
        return [
            'invalid string' => ['INVALID'],
            'null' => [null],
            'array' => [[]],
            'object' => [new \stdClass()],
            'integer 123' => [123],
            'float' => [1.23],
            'integer 0' => [0],
            'integer 1' => [1],
            'empty string' => [''],
        ];
    }
}

class StringableObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
