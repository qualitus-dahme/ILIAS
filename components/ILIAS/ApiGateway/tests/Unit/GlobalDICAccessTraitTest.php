<?php

declare(strict_types=1);

namespace Tests\Unit;

use ilDBInterface;
use ILIAS\ApiGateway\GlobalDICAccessTrait;
use ILIAS\DI\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(LocalDIC::class)]

class GlobalDICAccessTraitTest extends TestCase
{
    private MockObject&Container $containerMock;

    #[\Override]
    protected function setUp(): void
    {
        global $DIC;

        $DIC = $this->containerMock = $this->createMock(Container::class);
    }

    public function testReturnsDatabase(): void
    {
        $expected = $this->createMock(ilDBInterface::class);

        $this->containerMock->expects(self::once())->method('database')->willReturn($expected);

        $actual = (new class
        {
            use GlobalDICAccessTrait;

            public function getValue(): ilDBInterface
            {
                return $this->getDatabase();
            }
        })->getValue();

        $this->assertSame($expected, $actual);
    }

    public function testThrowsExceptionInCaseOfDatabaseIsNotSet(): void
    {
        global $DIC;

        $DIC = null;

        $this->containerMock->expects(self::never())->method('database');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No database connection');

        new class
        {
            use GlobalDICAccessTrait;

            public function __construct()
            {
                $this->getDatabase();
            }
        };
    }
}
