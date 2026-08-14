<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use ILIAS\ApiGateway\Logging\WebserviceLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ilLogger;
use ilLogLevel;
use Override;

final class WebserviceLoggerTest extends TestCase
{
    private WebserviceLogger $webserviceLogger;
    private MockObject&ilLogger $ilLoggerMock;

    #[Override]
    protected function setUp(): void
    {
        $this->webserviceLogger = new WebserviceLogger(
            $this->ilLoggerMock = $this->createMock(ilLogger::class),
        );
    }

    #[DataProvider('specificLogLevelsDataProvider')]
    public function testSpecificLogLevelMethodsCallIlLogger(
        string $method
    ): void {
        $message = 'Test message';
        $context = ['key' => 'value'];

        $this->ilLoggerMock->expects(self::once())
            ->method($method)
            ->with($message, $context);

        $this->webserviceLogger->$method($message, $context);
    }

    #[DataProvider('specificLogLevelsDataProvider')]
    public function testSpecificLogLevelMethodsCallIlLoggerWithStringableMessage(
        string $method
    ): void {
        $messageObject = new DummyStringableObject('Stringable message');
        $context = ['key' => 'value'];

        $this->ilLoggerMock->expects(self::once())
            ->method($method)
            ->with((string) $messageObject, $context); // Message is cast to string

        $this->webserviceLogger->$method($messageObject, $context);
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function specificLogLevelsDataProvider(): array
    {
        return [
            'emergency' => ['emergency', LogLevel::EMERGENCY, ilLogLevel::EMERGENCY],
            'alert' => ['alert', LogLevel::ALERT, ilLogLevel::ALERT],
            'critical' => ['critical', LogLevel::CRITICAL, ilLogLevel::CRITICAL],
            'error' => ['error', LogLevel::ERROR, ilLogLevel::ERROR],
            'warning' => ['warning', LogLevel::WARNING, ilLogLevel::WARNING],
            'notice' => ['notice', LogLevel::NOTICE, ilLogLevel::NOTICE],
            'info' => ['info', LogLevel::INFO, ilLogLevel::INFO],
            'debug' => ['debug', LogLevel::DEBUG, ilLogLevel::DEBUG],
        ];
    }

    #[DataProvider('genericLogLevelsDataProvider')]
    public function testLogMethodMapsPsrLogLevelsCorrectly(
        string $psrLevel,
        int $expectedIlLevel
    ): void {
        $message = 'Generic log message';
        $context = ['transaction_id' => '123'];

        $this->ilLoggerMock->expects(self::once())
            ->method('log')
            ->with($message, $expectedIlLevel, $context);

        $this->webserviceLogger->log($psrLevel, $message, $context);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function genericLogLevelsDataProvider(): array
    {
        return [
            'emergency' => [LogLevel::EMERGENCY, ilLogLevel::EMERGENCY],
            'alert' => [LogLevel::ALERT, ilLogLevel::ALERT],
            'critical' => [LogLevel::CRITICAL, ilLogLevel::CRITICAL],
            'error' => [LogLevel::ERROR, ilLogLevel::ERROR],
            'warning' => [LogLevel::WARNING, ilLogLevel::WARNING],
            'notice' => [LogLevel::NOTICE, ilLogLevel::NOTICE],
            'info' => [LogLevel::INFO, ilLogLevel::INFO],
            'debug' => [LogLevel::DEBUG, ilLogLevel::DEBUG],
        ];
    }

    public function testLogMethodMapsPsrLogLevelsCorrectlyWithStringableMessage(): void
    {
        $psrLevel = LogLevel::INFO;
        $expectedIlLevel = ilLogLevel::INFO;
        $messageObject = new DummyStringableObject('Stringable generic message');
        $context = ['transaction_id' => '123'];

        $this->ilLoggerMock->expects(self::once())
            ->method('log')
            ->with((string) $messageObject, $expectedIlLevel, $context);

        $this->webserviceLogger->log($psrLevel, $messageObject, $context);
    }

    #[DataProvider('mixedLevelInputDataProvider')]
    public function testLogMethodHandlesMixedLevelsCorrectly(
        mixed $psrLevelInput,
        int $expectedIlLevel
    ): void {
        $message = 'Message for mixed level';
        $context = ['test' => 'mixed'];

        $this->ilLoggerMock->expects(self::once())
            ->method('log')
            ->with($message, $expectedIlLevel, $context);

        $this->webserviceLogger->log($psrLevelInput, $message, $context);
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function mixedLevelInputDataProvider(): array
    {
        return [
            'string_emergency' => [LogLevel::EMERGENCY, ilLogLevel::EMERGENCY],
            'string_info' => [LogLevel::INFO, ilLogLevel::INFO],
            'scalar_int' => [123, ilLogLevel::INFO], // Not a known PSR level, falls back to INFO
            'scalar_float' => [123.45, ilLogLevel::INFO], // Not a known PSR level, falls back to INFO
            'scalar_bool_true' => [true, ilLogLevel::INFO], // '1', not a known PSR level, falls back to INFO
            'scalar_bool_false' => [false, ilLogLevel::INFO], // '', not a known PSR level, falls back to INFO
            'stringable_object_info' => [new DummyStringableObject(LogLevel::INFO), ilLogLevel::INFO],
            'stringable_object_custom' => [new DummyStringableObject('custom_level'), ilLogLevel::INFO], // Not a known PSR level, falls back to INFO
            'null_level' => [null, ilLogLevel::INFO], // Not stringable, falls back to INFO
            'array_level' => [['foo' => 'bar'], ilLogLevel::INFO], // Not stringable, falls back to INFO
            'object_without_tostring' => [new \stdClass(), ilLogLevel::INFO], // Not stringable, falls back to INFO
            'unknown_string' => ['non_existent_level', ilLogLevel::INFO], // Not a known PSR level, falls back to INFO
        ];
    }
}

// Dummy Stringable class for testing messages and levels
readonly class DummyStringableObject implements \Stringable
{
    public function __construct(private string $value)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
