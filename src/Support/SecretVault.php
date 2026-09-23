<?php

namespace Lowseekai\OAuthConnect\Support;

use Flarum\Foundation\Config;
use RuntimeException;

/**
 * Encrypts short-lived secrets without relying on illuminate/encryption,
 * which is not included by Flarum 2.x.
 */
class SecretVault
{
    private const PREFIX = 's1.';

    public function __construct(private Config $config)
    {
    }

    public function encryptString(string $value): string
    {
        if (! function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('The PHP Sodium extension is required to protect OAuth secrets.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($value, $nonce, $this->key());

        return self::PREFIX.$this->encode($nonce.$ciphertext);
    }

    public function decryptString(string $payload): string
    {
        if (! function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('The PHP Sodium extension is required to protect OAuth secrets.');
        }

        if (! str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Unsupported OAuth secret format.');
        }

        $decoded = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if ($decoded === false || strlen($decoded) <= $nonceLength + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Invalid OAuth secret payload.');
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($decoded, $nonceLength),
            substr($decoded, 0, $nonceLength),
            $this->key()
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt OAuth secret.');
        }

        return $plaintext;
    }

    private function key(): string
    {
        $key = base64_decode((string) ($this->config['oauth_connect.secret_key'] ?? ''), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Configure a dedicated 32-byte OAuth secret encryption key.');
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return base64_encode($value);
    }
}
