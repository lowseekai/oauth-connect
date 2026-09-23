<?php

namespace Lowseekai\OAuthConnect\Repositories;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Models\Client;
use Lowseekai\OAuthConnect\Support\ScopeRegistry;
use Lowseekai\OAuthConnect\Support\SecretVault;
use Lowseekai\OAuthConnect\Support\Translation;

class ApplicationRepository
{
    private $clients;
    private $scopes;
    private $settings;
    private $translation;
    private $database;
    private $vault;

    public function __construct(
        ClientRepository $clients,
        ScopeRegistry $scopes,
        SettingsRepositoryInterface $settings,
        Translation $translation,
        ConnectionInterface $database,
        SecretVault $vault
    ) {
        $this->clients = $clients;
        $this->scopes = $scopes;
        $this->settings = $settings;
        $this->translation = $translation;
        $this->database = $database;
        $this->vault = $vault;
    }

    public function create(int $userId, array $data): Application
    {
        $name = $this->requiredString($data['name'] ?? null, 120, 'application_name_required');
        $description = $this->requiredString($data['description'] ?? null, 2000, 'application_description_required');
        $homepageUrl = $this->homepageUrl($data['homepage_url'] ?? null);
        $redirectUris = $this->redirectUris($data['redirect_uris'] ?? $data['redirect_uri'] ?? []);
        $scopes = $this->scopes->normalize($data['scopes'] ?? $this->scopes->defaults());

        return $this->database->transaction(function () use ($userId, $name, $description, $homepageUrl, $redirectUris, $scopes, $data) {
            // Lock the applicant row so concurrent identical submissions cannot both pass the duplicate check.
            $this->database->table('users')->where('id', $userId)->lockForUpdate()->first();
            $existing = Application::where('user_id', $userId)
                ->where('name', $name)
                ->whereIn('status', ['pending', 'approved'])
                ->lockForUpdate()
                ->get();

            foreach ($existing as $candidate) {
                if ($candidate->redirectUris() === $redirectUris) {
                    throw new InvalidArgumentException($this->trans('admin.errors.application_duplicate', [], 'An application with the same name and redirect URI already exists.'));
                }
            }

            $application = new Application();
            $application->user_id = $userId;
            $application->name = $name;
            $application->description = $description;
            $application->homepage_url = $homepageUrl;
            $application->redirect_uris = json_encode($redirectUris, JSON_UNESCAPED_SLASHES);
            $application->requested_scopes = json_encode($scopes, JSON_UNESCAPED_SLASHES);
            $application->application_note = $this->nullableString($data['application_note'] ?? null, 2000);
            $application->status = 'pending';
            $application->save();

            return $application;
        });
    }

    public function findForUser(int $id, int $userId): ?Application
    {
        return Application::where('id', $id)->where('user_id', $userId)->first();
    }

    public function find(int $id): ?Application
    {
        return Application::where('id', $id)->first();
    }

    public function approve(Application $application, int $adminId, array $data = []): array
    {
        if ($application->status !== 'pending') {
            throw new InvalidArgumentException($this->trans('admin.errors.application_not_pending', [], 'This application is no longer pending.'));
        }

        $redirectUris = array_key_exists('redirect_uris', $data)
            ? $this->redirectUris($data['redirect_uris'])
            : $application->redirectUris();
        $requestedScopes = $application->requestedScopeList();
        $scopes = array_key_exists('approved_scopes', $data)
            ? $this->scopes->normalize(array_values(array_intersect($this->scopeInput($data['approved_scopes']), $requestedScopes)))
            : $this->scopes->normalize($requestedScopes);
        $reviewNote = $this->nullableString($data['review_note'] ?? null, 5000);

        return $this->database->transaction(function () use ($application, $adminId, $redirectUris, $scopes, $reviewNote) {
            $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->status !== 'pending') {
                throw new InvalidArgumentException($this->trans('admin.errors.application_not_pending', [], 'This application is no longer pending.'));
            }

            [$client, $secret] = $this->clients->create([
                'name' => $application->name,
                'description' => $application->description,
                'homepage_url' => $application->homepage_url,
                'redirect_uris' => $redirectUris,
                'scopes' => $scopes,
                'oidc_enabled' => count(array_intersect($scopes, ['openid', 'profile', 'email'])) > 0,
                'is_enabled' => true,
            ]);

            $client->approval_status = 'approved';
            $client->owner_user_id = $application->user_id;
            $client->application_id = $application->id;
            $client->source = 'application';
            $client->approved_by = $adminId;
            $client->approved_at = Carbon::now();
            $client->save();

            $application->redirect_uris = json_encode($redirectUris, JSON_UNESCAPED_SLASHES);
            $application->approved_scopes = json_encode($scopes, JSON_UNESCAPED_SLASHES);
            $application->status = 'approved';
            $application->review_note = $reviewNote;
            $application->client_secret_encrypted = $this->vault->encryptString($secret);
            $application->reviewed_by = $adminId;
            $application->reviewed_at = Carbon::now();
            $application->save();

            return [$application, $client, $secret];
        });
    }

    public function reject(Application $application, int $adminId, string $reason): Application
    {
        $reason = $this->requiredString($reason, 5000, 'review_note_required');

        return $this->transition($application, 'rejected', $adminId, $reason);
    }

    public function transition(Application $application, string $status, int $actorId, ?string $reason = null): Application
    {
        return $this->database->transaction(function () use ($application, $status, $actorId, $reason) {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $allowed = [
                'rejected' => ['pending'],
                'withdrawn' => ['pending'],
                'cancelled' => ['pending'],
                'pending' => ['rejected'],
            ];

            if (! isset($allowed[$status]) || ! in_array($locked->status, $allowed[$status], true)) {
                throw new InvalidArgumentException('Invalid application state transition.');
            }

            $locked->status = $status;
            if ($reason !== null) {
                $locked->review_note = $reason;
            }
            $locked->reviewed_by = $actorId;
            $locked->reviewed_at = Carbon::now();
            $locked->save();

            return $locked;
        });
    }

    public function serialize(Application $application): array
    {
        return [
            'id' => (int) $application->id,
            'user_id' => (int) $application->user_id,
            'username' => $application->user ? $application->user->username : null,
            'name' => $application->name,
            'description' => $application->description,
            'homepage_url' => $application->homepage_url,
            'redirect_uris' => $application->redirectUris(),
            'requested_scopes' => $application->requestedScopeList(),
            'approved_scopes' => $application->approvedScopeList(),
            'status' => $application->status,
            'client_id' => $application->client ? $application->client->client_id : null,
            'client_enabled' => $application->client ? (bool) $application->client->is_enabled : null,
            'application_note' => $application->application_note,
            'review_note' => $application->review_note,
            'reviewed_by' => $application->reviewed_by ? (int) $application->reviewed_by : null,
            'reviewed_at' => $this->date($application->reviewed_at),
            'created_at' => $this->date($application->created_at),
            'updated_at' => $this->date($application->updated_at),
        ];
    }

    public function consumeSecret(Application $application): ?string
    {
        return $this->database->transaction(function () use ($application) {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved' || ! $locked->client_secret_encrypted) {
                return null;
            }
            $secret = $this->vault->decryptString($locked->client_secret_encrypted);
            $locked->client_secret_encrypted = null;
            $locked->save();

            return $secret;
        });
    }

    private function redirectUris($value): array
    {
        $items = is_string($value) ? preg_split('/\r\n|\r|\n/', $value) : (is_array($value) ? $value : []);
        $items = array_values(array_unique(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $items), 'strlen')));

        if ($items === []) {
            throw new InvalidArgumentException($this->trans('admin.errors.redirect_uri_required', [], 'At least one redirect URI is required.'));
        }

        foreach ($items as $uri) {
            $this->assertRedirectUri($uri);
        }

        return $items;
    }

    private function assertRedirectUri(string $uri): void
    {
        $parts = parse_url($uri);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowLocal = (bool) (int) $this->settings->get('lowseekai.oauth-connect.allow_local_redirects', 0);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (! $parts || $scheme === '' || $host === '' || ($scheme !== 'https' && ! ($allowLocal && $local && $scheme === 'http'))) {
            throw new InvalidArgumentException($this->trans('admin.errors.application_redirect_uri_invalid', ['uri' => $uri], 'Invalid application redirect URI: {uri}'));
        }

        if (isset($parts['fragment']) || str_contains($uri, '*')) {
            throw new InvalidArgumentException($this->trans('admin.errors.application_redirect_uri_invalid', ['uri' => $uri], 'Invalid application redirect URI: {uri}'));
        }
    }

    private function homepageUrl($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL) || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException($this->trans('admin.errors.application_homepage_invalid', [], 'Application homepage must be a valid HTTPS URL.'));
        }

        return mb_substr($value, 0, 2048);
    }

    private function requiredString($value, int $max, string $error): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException($this->trans('admin.errors.'.$error, [], 'This field is required.'));
        }

        return mb_substr($value, 0, $max);
    }

    private function nullableString($value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }

    private function scopeInput($value): array
    {
        return is_array($value) ? $value : (preg_split('/\s+/', trim((string) $value)) ?: []);
    }

    private function trans(string $key, array $params = [], string $fallback = ''): string
    {
        return $this->translation->trans($key, $params, $fallback);
    }
}
