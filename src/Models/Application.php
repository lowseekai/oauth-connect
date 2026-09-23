<?php

namespace Lowseekai\OAuthConnect\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class Application extends AbstractModel
{
    protected $table = 'oauth_connect_applications';

    public $timestamps = true;

    protected $dates = [
        'reviewed_at',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'application_id', 'id');
    }

    public function redirectUris(): array
    {
        return $this->decodeList($this->redirect_uris);
    }

    public function requestedScopeList(): array
    {
        return $this->decodeList($this->requested_scopes);
    }

    public function approvedScopeList(): array
    {
        return $this->decodeList($this->approved_scopes);
    }

    private function decodeList($value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'strlen')) : [];
    }
}
