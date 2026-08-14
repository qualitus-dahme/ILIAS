<?php

declare(strict_types=1);

namespace Tests\Unit\Webservice\Domain\Model;

use ILIAS\ApiGateway\Webservice\Domain\Model\Payload;
use PHPUnit\Framework\TestCase;

class PayloadTest extends TestCase
{
    public function testHasAccessorToRawData(): void
    {
        $payload = new Payload(data: ['key' => 'value']);

        self::assertEquals(['key' => 'value'], $payload->getData());
    }

    public function testHasAccessorToHeaders(): void
    {
        $payload = new Payload(
            headers: ['foo' => 'bar']
        );

        self::assertEquals(
            ['foo' => 'bar'],
            $payload->getHeaders()
        );
    }

    public function testHasAccessorToBody(): void
    {
        $payload = new Payload(body: 'foobarbaz');

        self::assertEquals('foobarbaz', $payload->getBody());
    }

    public function testWithHeaderReturnsNewInstanceWithAddedHeader(): void
    {
        $payload = new Payload();
        $newPayload = $payload->withHeader('Content-Type', 'application/json');

        self::assertNotSame($payload, $newPayload);
        self::assertEquals([], $payload->getHeaders());
        self::assertEquals(
            ['Content-Type' => 'application/json'],
            $newPayload->getHeaders()
        );
    }

    public function testWithBodyReturnsNewInstanceWithAddedBody(): void
    {
        $payload = new Payload();
        $newPayload = $payload->withBody('foobarbaz');

        self::assertNotSame($payload, $newPayload);
        self::assertEquals('', $payload->getBody());
        self::assertEquals('foobarbaz', $newPayload->getBody());
    }
}
