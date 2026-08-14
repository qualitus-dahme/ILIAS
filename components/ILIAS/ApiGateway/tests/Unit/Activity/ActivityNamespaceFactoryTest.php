<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use ILIAS\ApiGateway\Activity\ActivityNamespaceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityNamespaceFactoryTest extends TestCase
{
    private ActivityNamespaceFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new ActivityNamespaceFactory();
    }

    #[DataProvider('classNameToPathProvider')]
    public function testGetsNamespacePath(
        string $namespace,
        string $expected,
    ): void {
        $actual = $this->factory->create($namespace);

        self::assertSame(
            $expected,
            $actual->getPath()
        );
    }

    #[DataProvider('invalidClassNamesProvider')]
    public function testThrowsExceptionForInvalidClassName(
        string $className,
        string $expected,
    ): void {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(
            "{$expected} is not a proper name for a dependency."
        );

        $this->factory->create($className);
    }

    /**
     * @return array<string, string[]>
     */
    public static function classNameToPathProvider(): array
    {
        return [
            'standard activity name' => [
                'Foo\\Bar\\ActivityName',
                '/foo/bar/activityname'
            ],
            'activity name ending with Query' => [
                'Foo\\Bar\\MyQuery',
                '/foo/bar/myquery'
            ],
            'activity name with Query in middle' => [
                'Foo\\Bar\\QueryActivity',
                '/foo/bar'
            ],
            'activity name starting with Query' => [
                'Foo\\Bar\\QueryFoo',
                '/foo/bar/foo'
            ],
            'activity name is just Query' => [
                'Foo\\Bar\\Query',
                '/foo/bar'
            ],
            'activity name with mixed case' => [
                'Foo\\Bar\\activityName',
                '/foo/bar/activityname'
            ],
            'vendor and component with mixed case' => [
                'vEnDoR\\cOmPoNeNt\\ActivityName',
                '/vendor/component/activityname'
            ],
            'vendor and component with numbers' => [
                'Vendor1\\Component2\\ActivityName',
                '/vendor1/component2/activityname'
            ],
            'class name with more than three parts' => [
                'Foo\\Bar\\Baz\\FooBarBaz\\SubComponent\\ActivityName',
                '/foo/bar/activityname'
            ],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function invalidClassNamesProvider(): array
    {
        return [
            'two parts' => ['Foo\\Bar', 'Foo\Bar'],
            'one part' => ['Foo', 'Foo'],
            'leading space' => [' Foo\\Bar\\Baz', ' Foo\Bar\Baz'],
            'trailing space' => ['Foo\\Bar\\Baz ', 'Foo\Bar\Baz '],
            'leading special char' => ['@Foo\\Bar\\Baz', '@Foo\Bar\Baz'],
            'trailing special char' => ['Foo\\Bar\\Baz@', 'Foo\Bar\Baz@'],
            'invalid char in vendor' => ['F@o\\Bar\\Baz', 'F@o\Bar\Baz'],
            'invalid char in component' => ['Foo\\B@r\\Baz', 'Foo\B@r\Baz'],
            'invalid char in name' => ['Foo\\Bar\\B@z', 'Foo\Bar\B@z'],
        ];
    }
}
