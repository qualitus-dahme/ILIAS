<?php

declare(strict_types=1);

namespace Tests\Unit\Webservice\Infrastructure;

use ILIAS\ApiGateway\Configuration\Domain\Model\WebConfig;
use ILIAS\ApiGateway\Webservice\Domain\Enum\ServiceProtocol;
use ILIAS\ApiGateway\Webservice\Domain\Model\Payload;
use ILIAS\ApiGateway\Webservice\Infrastructure\RestWebservice;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

final class RestWebserviceTest extends TestCase
{
    private RestWebservice $webservice;
    private WebConfig&MockObject $config;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->webservice = new RestWebservice(
            $this->config = $this->createConfiguredMock(WebConfig::class, [
                'getBaseUrl' => 'foo',
                'isEnabled' => true,
            ]),
        );
    }

    public function testHasAccessorToProtocol(): void
    {
        $expected = ServiceProtocol::REST;

        $this->config->method('getProtocol')->willReturn($expected);

        self::assertSame(
            $expected,
            $this->webservice->getProtocol(),
        );
    }

    public function testHandlesPayloadWithoutDebug(): void
    {
        $payloadData = ['key' => 'value'];
        $payload = new Payload($payloadData);

        $actual = $this->webservice->handle($payload);

        $expected = '{"success":true,"data":{"key":"value"}}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesPayloadWithDebug(): void
    {
        $payloadData = ['key' => 'value'];
        $payload = new Payload($payloadData);

        $this->config->method('isDebugEnabled')->willReturn(true);

        $actual = $this->webservice->handle($payload);

        $expected = <<<JSON
{
    "success": true,
    "data": {
        "key": "value"
    }
}
JSON;

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesEmptyPayload(): void
    {
        $payload = new Payload();

        $actual = $this->webservice->handle($payload);

        $expected = '{"success":true,"data":null}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesPayloadWithComplexData(): void
    {
        $payloadData = [
            'array' => [1, 2, 3],
            'object' => (object) ['key' => 'value'],
        ];
        $payload = new Payload($payloadData);

        $actual = $this->webservice->handle($payload);

        $expected = '{"success":true,"data":{"array":[1,2,3],"object":{"key":"value"}}}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesPayloadWithSpecialCharacters(): void
    {
        $payloadData = ['text' => 'Special characters: ñ, ü, 漢字'];
        $payload = new Payload($payloadData);

        $actual = $this->webservice->handle($payload);

        $expected = '{"success":true,"data":{"text":"Special characters: \u00f1, \u00fc, \u6f22\u5b57"}}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testThrowsExceptionOnInvalidPayload(): void
    {
        // We use a malformed UTF-8 string, which cannot be encoded by json_encode.
        $payload = new Payload("\xB1\x31");

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Failed to encode payload data');

        $this->webservice->handle($payload);
    }

    public function testHandlesErrorWithoutDetailsOrDebug(): void
    {
        $exception = new RuntimeException('Something went wrong');

        $actual = $this->webservice->handleError($exception);

        $expected = '{"success":false,"error":"Something went wrong"}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesErrorWithDetails(): void
    {
        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->config->method('isLoggingDetailsEnabled')->willReturn(true);

        $exception = new RuntimeException('Something went wrong');

        $actual = $this->webservice->handleError($exception);
        /**
         * @var array<string, mixed>
         */
        $actualBody = json_decode($actual->getBody(), true);

        self::assertFalse($actualBody['success']);
        self::assertSame('Something went wrong', $actualBody['error']);
        self::assertIsArray($actualBody['stack']);
        self::assertNotEmpty($actualBody['stack']);
    }

    public function testHandlesErrorWithDebug(): void
    {
        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->config->method('isLoggingDetailsEnabled')->willReturn(false);

        $exception = new RuntimeException('Something went wrong');

        $actual = $this->webservice->handleError($exception);

        $expected = <<<JSON
{
    "success": false,
    "error": "Something went wrong"
}
JSON;

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesErrorWithDetailsAndDebug(): void
    {
        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->config->method('isLoggingDetailsEnabled')->willReturn(true);

        $exception = new RuntimeException('Something went wrong');

        $actual = $this->webservice->handleError($exception);
        /**
         * @var array<string, mixed>
         */
        $actualBody = json_decode($actual->getBody(), true);

        self::assertFalse($actualBody['success']);
        self::assertSame('Something went wrong', $actualBody['error']);
        self::assertIsArray($actualBody['stack']);
        self::assertNotEmpty($actualBody['stack']);
        self::assertStringContainsString("\n", $actual->getBody());
    }

    public function testHandlesErrorWithSpecialCharacters(): void
    {
        $exception = new RuntimeException('Error: ñ, ü, 漢字');

        $actual = $this->webservice->handleError($exception);

        $expected = '{"success":false,"error":"Error: ñ, ü, 漢字"}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandlesErrorWithSlashes(): void
    {
        $exception = new RuntimeException('Error: path/to/file');

        $actual = $this->webservice->handleError($exception);

        $expected = '{"success":false,"error":"Error: path/to/file"}';

        self::assertSame(
            $expected,
            $actual->getBody(),
        );
    }

    public function testHandleReturnsCorrectHeaders(): void
    {
        $payloadData = ['key' => 'value'];
        $payload = new Payload($payloadData);

        $actualPayload = $this->webservice->handle($payload);
        $actualBody = $actualPayload->getBody();
        $actualHeaders = $actualPayload->getHeaders();

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Content-Length' => (string) strlen($actualBody),
        ];

        self::assertEquals($expectedHeaders, $actualHeaders);
    }

    public function testHandleErrorReturnsCorrectHeaders(): void
    {
        $exception = new RuntimeException('Something went wrong');

        $actualPayload = $this->webservice->handleError($exception);
        $actualBody = $actualPayload->getBody();
        $actualHeaders = $actualPayload->getHeaders();

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Content-Length' => (string) strlen($actualBody),
        ];

        self::assertEquals($expectedHeaders, $actualHeaders);
    }
}
