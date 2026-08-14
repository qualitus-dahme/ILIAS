<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Domain\Model;

use ILIAS\ApiGateway\Configuration\Domain\Model\AuthConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthConfig::class)]
final class AuthConfigTest extends TestCase
{
    private AuthConfig $model;
    private string $issuer;
    private string $secretKey;
    private string $encryptionAlgo;
    private string $hashAlgo;
    private int $accessTokenExpiry;
    private int $refreshTokenExpiry;

    #[\Override]
    protected function setUp(): void
    {
        $this->model = new AuthConfig(
            $this->issuer = 'http://localhost:1234',
            $this->secretKey = 'super-secret-key',
            $this->encryptionAlgo = 'foo',
            $this->hashAlgo = 'bar',
            $this->accessTokenExpiry = 1234567890,
            $this->refreshTokenExpiry = 9876543210,
        );
    }

    public function testHasAccessorToIssuer(): void
    {
        $this->assertSame(
            $this->issuer,
            $this->model->getIssuer(),
        );
    }

    public function testHasAccessorToSecretKey(): void
    {
        $this->assertSame(
            $this->secretKey,
            $this->model->getSecretKey(),
        );
    }

    public function testHasAccessorToEncryptionAlgo(): void
    {
        $this->assertSame(
            $this->encryptionAlgo,
            $this->model->getEncryptionAlgo(),
        );
    }

    public function testHasAccessorToHashAlgo(): void
    {
        $this->assertSame(
            $this->hashAlgo,
            $this->model->getHashAlgo(),
        );
    }

    public function testHasAccessorToAccessTokenExpiry(): void
    {
        $this->assertSame(
            $this->accessTokenExpiry,
            $this->model->getAccessTokenExpiry(),
        );
    }

    public function testHasAccessorToRefreshTokenExpiry(): void
    {
        $this->assertSame(
            $this->refreshTokenExpiry,
            $this->model->getRefreshTokenExpiry(),
        );
    }
}
