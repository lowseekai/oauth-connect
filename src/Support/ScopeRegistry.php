<?php

namespace Lowseekai\OAuthConnect\Support;

use InvalidArgumentException;
use Lowseekai\OAuthConnect\Models\Client;

class ScopeRegistry
{
    private $translation;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }

    public function all(): array
    {
        return [
            'openid' => $this->trans('scopes.openid', [], 'Sign in with OpenID Connect'),
            'profile' => $this->trans('scopes.profile', [], 'Read standard profile claims'),
            'email' => $this->trans('scopes.email', [], 'Read standard email claims'),
            'user.read' => $this->trans('scopes.user_read', [], 'Read basic profile'),
            'user.email' => $this->trans('scopes.user_email', [], 'Read email address'),
            'user.stats' => $this->trans('scopes.user_stats', [], 'Read public activity counters'),
            'user.moderation' => $this->trans('scopes.user_moderation', [], 'Read moderation status'),
            'user.trust' => $this->trans('scopes.user_trust', [], 'Read trust level when installed'),
        ];
    }

    public function defaults(): array
    {
        return ['user.read'];
    }

    public function normalize($scope, ?Client $client = null): array
    {
        $scopes = is_array($scope)
            ? $scope
            : preg_split('/\s+/', trim((string) $scope));

        $scopes = array_values(array_unique(array_filter($scopes ?: [], 'strlen')));

        if ($scopes === []) {
            $scopes = $this->defaults();
        }

        if ((in_array('profile', $scopes, true) || in_array('email', $scopes, true)) && ! in_array('openid', $scopes, true)) {
            throw new InvalidArgumentException($this->trans('errors.openid_required', [], 'openid scope is required when requesting profile or email claims.'));
        }

        $known = array_keys($this->all());
        $allowedByClient = $client ? $client->scopeList() : $known;
        $allowedLegacy = $allowedByClient === []
            ? array_values(array_filter($known, function ($scopeName) {
                return ! $this->isOidcScope($scopeName);
            }))
            : array_intersect($known, $allowedByClient);

        foreach ($scopes as $scopeName) {
            if (! in_array($scopeName, $known, true)) {
                throw new InvalidArgumentException($this->trans('errors.unknown_scope', [
                    'scope' => $scopeName,
                ], 'Unknown scope: {scope}'));
            }

            if ($this->isOidcScope($scopeName)) {
                if ($client && (! $client->oidcEnabled() || ! $this->oidcScopeAllowed($scopeName, $allowedByClient))) {
                    throw new InvalidArgumentException($this->trans('errors.scope_not_allowed', [
                        'scope' => $scopeName,
                    ], 'Scope is not allowed for this client: {scope}'));
                }

                continue;
            }

            if (! in_array($scopeName, $allowedLegacy, true)) {
                throw new InvalidArgumentException($this->trans('errors.scope_not_allowed', [
                    'scope' => $scopeName,
                ], 'Scope is not allowed for this client: {scope}'));
            }
        }

        if (! in_array('user.read', $scopes, true)) {
            array_unshift($scopes, 'user.read');
        }

        return array_values(array_unique($scopes));
    }

    public function containsOpenId(array $scopes): bool
    {
        return in_array('openid', $scopes, true);
    }

    public function toString(array $scopes): string
    {
        return implode(' ', array_values(array_unique($scopes)));
    }

    private function trans(string $key, array $params = [], string $fallback = ''): string
    {
        return $this->translation->trans($key, $params, $fallback);
    }

    private function isOidcScope(string $scopeName): bool
    {
        return in_array($scopeName, ['openid', 'profile', 'email'], true);
    }

    private function oidcScopeAllowed(string $scopeName, array $allowedByClient): bool
    {
        if ($allowedByClient === []) {
            return true;
        }

        $equivalents = [
            'openid' => ['openid', 'user.read'],
            'profile' => ['profile', 'user.read'],
            'email' => ['email', 'user.email'],
        ];

        foreach ($equivalents[$scopeName] ?? [$scopeName] as $allowedScope) {
            if (in_array($allowedScope, $allowedByClient, true)) {
                return true;
            }
        }

        return false;
    }
}
