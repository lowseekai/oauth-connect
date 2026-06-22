<?php

namespace ISeekUp\OAuthConnect\Support;

use Carbon\Carbon;
use Flarum\Foundation\Application;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use RuntimeException;

class OpenIdConnect
{
    private const PRIVATE_KEY_SETTING = 'iseekup.oauth-connect.oidc_private_key';
    private const PUBLIC_KEY_SETTING = 'iseekup.oauth-connect.oidc_public_key';
    private const ISSUER_SETTING = 'iseekup.oauth-connect.oidc_issuer';

    private $app;
    private $settings;
    private $scopes;

    public function __construct(
        Application $app,
        SettingsRepositoryInterface $settings,
        ScopeRegistry $scopes
    ) {
        $this->app = $app;
        $this->settings = $settings;
        $this->scopes = $scopes;
    }

    public function issuer(): string
    {
        $configured = trim((string) $this->settings->get(self::ISSUER_SETTING, ''));

        return rtrim($configured !== '' ? $configured : $this->baseUrl(), '/');
    }

    public function configuration(): array
    {
        return [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->baseUrl().'/oauth2/authorize',
            'token_endpoint' => $this->baseUrl().'/oauth2/token',
            'userinfo_endpoint' => $this->apiBaseUrl().'/oauth/user',
            'jwks_uri' => $this->baseUrl().'/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => array_keys($this->scopes->all()),
            'claims_supported' => [
                'iss',
                'sub',
                'aud',
                'exp',
                'iat',
                'nonce',
                'name',
                'preferred_username',
                'picture',
                'email',
                'email_verified',
            ],
        ];
    }

    public function jwks(): array
    {
        $details = $this->publicKeyDetails();
        $rsa = $details['rsa'] ?? null;

        if (! is_array($rsa) || ! isset($rsa['n'], $rsa['e'])) {
            throw new RuntimeException('OIDC public key is not an RSA key.');
        }

        return [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'kid' => $this->keyId(),
                'alg' => 'RS256',
                'n' => $this->base64Url($rsa['n']),
                'e' => $this->base64Url($rsa['e']),
            ]],
        ];
    }

    public function idToken(User $user, string $clientId, array $scopes, ?string $nonce, int $lifetime): string
    {
        $now = Carbon::now()->timestamp;
        $claims = [
            'iss' => $this->issuer(),
            'sub' => (string) $user->id,
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + max(60, $lifetime),
        ];

        if ($nonce !== null && $nonce !== '') {
            $claims['nonce'] = $nonce;
        }

        if ($this->hasScope($scopes, ['profile'])) {
            $claims['name'] = $user->display_name ?: $user->username;
            $claims['preferred_username'] = $user->username;

            if ($user->avatar_url) {
                $claims['picture'] = $user->avatar_url;
            }
        }

        if ($this->hasScope($scopes, ['email', 'user.email'])) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = (bool) $user->is_email_confirmed;
        }

        return $this->jwt($claims);
    }

    private function jwt(array $claims): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => $this->keyId(),
        ];

        $segments = [
            $this->base64Url(json_encode($header)),
            $this->base64Url(json_encode($claims)),
        ];

        $signingInput = implode('.', $segments);
        $privateKey = openssl_pkey_get_private($this->privateKey());

        if (! $privateKey || ! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign OIDC ID token.');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function publicKeyDetails(): array
    {
        $publicKey = openssl_pkey_get_public($this->publicKey());

        if (! $publicKey) {
            throw new RuntimeException('Unable to load OIDC public key.');
        }

        $details = openssl_pkey_get_details($publicKey);

        if (! is_array($details)) {
            throw new RuntimeException('Unable to inspect OIDC public key.');
        }

        return $details;
    }

    private function privateKey(): string
    {
        [$privateKey] = $this->keyPair();

        return $privateKey;
    }

    private function publicKey(): string
    {
        [, $publicKey] = $this->keyPair();

        return $publicKey;
    }

    private function keyPair(): array
    {
        $privateKey = (string) $this->settings->get(self::PRIVATE_KEY_SETTING, '');
        $publicKey = (string) $this->settings->get(self::PUBLIC_KEY_SETTING, '');

        if ($privateKey !== '' && $publicKey !== '' && openssl_pkey_get_private($privateKey) && openssl_pkey_get_public($publicKey)) {
            return [$privateKey, $publicKey];
        }

        if (! function_exists('openssl_pkey_new')) {
            throw new RuntimeException('The PHP OpenSSL extension is required for OIDC.');
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (! $resource || ! openssl_pkey_export($resource, $privateKey)) {
            throw new RuntimeException('Unable to generate OIDC signing key.');
        }

        $details = openssl_pkey_get_details($resource);
        $publicKey = is_array($details) ? (string) ($details['key'] ?? '') : '';

        if ($publicKey === '') {
            throw new RuntimeException('Unable to export OIDC public key.');
        }

        $this->settings->set(self::PRIVATE_KEY_SETTING, $privateKey);
        $this->settings->set(self::PUBLIC_KEY_SETTING, $publicKey);

        return [$privateKey, $publicKey];
    }

    private function keyId(): string
    {
        return substr(hash('sha256', $this->publicKey()), 0, 16);
    }

    private function baseUrl(): string
    {
        return rtrim($this->app->url(), '/');
    }

    private function apiBaseUrl(): string
    {
        return $this->baseUrl().'/api';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function hasScope(array $scopes, array $needles): bool
    {
        foreach ($needles as $scope) {
            if (in_array($scope, $scopes, true)) {
                return true;
            }
        }

        return false;
    }
}
