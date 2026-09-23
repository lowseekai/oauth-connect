<?php

namespace Lowseekai\OAuthConnect\Tests\Unit;

use Flarum\Foundation\Config;
use Lowseekai\OAuthConnect\Support\SecretVault;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecretVaultTest extends TestCase
{
    private function vault(): SecretVault
    {
        return new SecretVault(new Config([
            'url' => 'https://forum.example',
            'oauth_connect' => ['secret_key' => base64_encode(random_bytes(32))],
        ]));
    }

    public function testRandomizedRoundTrip(): void
    {
        $vault = $this->vault();
        for ($i = 0; $i < 100; $i++) {
            $secret = bin2hex(random_bytes(36));
            $encrypted = $vault->encryptString($secret);
            self::assertSame($secret, $vault->decryptString($encrypted));
            self::assertNotSame($encrypted, $vault->encryptString($secret));
        }
    }

    public function testTamperingIsRejected(): void
    {
        $vault = $this->vault();
        $payload = base64_decode(substr($vault->encryptString('secret'), 3));
        $payload[strlen($payload) - 1] = chr(ord($payload[strlen($payload) - 1]) ^ 1);
        $this->expectException(RuntimeException::class);
        $vault->decryptString('s1.'.base64_encode($payload));
    }

    public function testWrongKeyIsRejected(): void
    {
        $payload = $this->vault()->encryptString('secret');
        $this->expectException(RuntimeException::class);
        $this->vault()->decryptString($payload);
    }

    public function testMissingKeyFailsClosed(): void
    {
        $vault = new SecretVault(new Config(['url' => 'https://forum.example']));
        $this->expectException(RuntimeException::class);
        $vault->encryptString('secret');
    }
}
