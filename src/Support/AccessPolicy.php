<?php

namespace ISeekUp\OAuthConnect\Support;

use Carbon\Carbon;
use Flarum\User\User;
use ISeekUp\OAuthConnect\Models\Client;

class AccessPolicy
{
    private $translation;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }

    public function defaults(): array
    {
        return [
            'enabled' => false,
            'require_email_confirmed' => false,
            'block_suspended' => true,
            'min_discussion_count' => null,
            'min_comment_count' => null,
            'min_account_age_days' => null,
            'last_seen_within_days' => null,
            'min_trust_level' => null,
        ];
    }

    public function normalize($policy): array
    {
        if (is_string($policy)) {
            $decoded = json_decode($policy, true);
            $policy = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($policy)) {
            $policy = [];
        }

        $normalized = $this->defaults();
        $normalized['enabled'] = $this->bool($policy['enabled'] ?? $normalized['enabled']);
        $normalized['require_email_confirmed'] = $this->bool($policy['require_email_confirmed'] ?? $normalized['require_email_confirmed']);
        $normalized['block_suspended'] = $this->bool($policy['block_suspended'] ?? $normalized['block_suspended']);

        foreach ([
            'min_discussion_count',
            'min_comment_count',
            'min_account_age_days',
            'last_seen_within_days',
            'min_trust_level',
        ] as $key) {
            $normalized[$key] = $this->nullableInteger($policy[$key] ?? null);
        }

        return $normalized;
    }

    public function allows(Client $client, User $user): bool
    {
        return $this->failureMessage($client, $user) === null;
    }

    public function failureMessage(Client $client, User $user): ?string
    {
        $reasons = $this->failures($client, $user);

        if ($reasons === []) {
            return null;
        }

        return implode(' ', $reasons);
    }

    public function failures(Client $client, User $user): array
    {
        $policy = $this->normalize($client->accessPolicy());

        if (! $policy['enabled']) {
            return [];
        }

        $failures = [];

        if ($policy['require_email_confirmed'] && ! (bool) $user->is_email_confirmed) {
            $failures[] = $this->trans('access_policy.failures.email_confirmed', [], 'Your email address must be confirmed.');
        }

        if ($policy['block_suspended'] && $this->isSuspended($user)) {
            $failures[] = $this->trans('access_policy.failures.suspended', [], 'Your account is suspended.');
        }

        if ($policy['min_discussion_count'] !== null && (int) $user->discussion_count < $policy['min_discussion_count']) {
            $failures[] = $this->trans('access_policy.failures.min_discussion_count', [
                'count' => $policy['min_discussion_count'],
            ], 'You must have at least {count} discussions.');
        }

        if ($policy['min_comment_count'] !== null && (int) $user->comment_count < $policy['min_comment_count']) {
            $failures[] = $this->trans('access_policy.failures.min_comment_count', [
                'count' => $policy['min_comment_count'],
            ], 'You must have at least {count} comments.');
        }

        if ($policy['min_account_age_days'] !== null && ! $this->joinedAtOrBefore($user, Carbon::now()->subDays($policy['min_account_age_days']))) {
            $failures[] = $this->trans('access_policy.failures.min_account_age_days', [
                'days' => $policy['min_account_age_days'],
            ], 'Your account must be at least {days} days old.');
        }

        if ($policy['last_seen_within_days'] !== null && ! $this->seenAtOrAfter($user, Carbon::now()->subDays($policy['last_seen_within_days']))) {
            $failures[] = $this->trans('access_policy.failures.last_seen_within_days', [
                'days' => $policy['last_seen_within_days'],
            ], 'You must have been active within the last {days} days.');
        }

        if ($policy['min_trust_level'] !== null) {
            if (! array_key_exists('trust_level', $user->getAttributes()) || (int) $user->getAttribute('trust_level') < $policy['min_trust_level']) {
                $failures[] = $this->trans('access_policy.failures.min_trust_level', [
                    'level' => $policy['min_trust_level'],
                ], 'Your trust level must be at least {level}.');
            }
        }

        return $failures;
    }

    private function bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function nullableInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function isSuspended(User $user): bool
    {
        if (! array_key_exists('suspended_until', $user->getAttributes())) {
            return false;
        }

        $value = $user->getAttribute('suspended_until');

        return $value ? Carbon::parse($value)->isFuture() : false;
    }

    private function joinedAtOrBefore(User $user, Carbon $threshold): bool
    {
        return $user->joined_at ? Carbon::parse($user->joined_at)->lessThanOrEqualTo($threshold) : false;
    }

    private function seenAtOrAfter(User $user, Carbon $threshold): bool
    {
        return $user->last_seen_at ? Carbon::parse($user->last_seen_at)->greaterThanOrEqualTo($threshold) : false;
    }

    private function trans(string $key, array $params = [], string $fallback = ''): string
    {
        return $this->translation->trans($key, $params, $fallback);
    }
}
