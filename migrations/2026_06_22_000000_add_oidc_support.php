<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('oauth_connect_clients', 'oidc_enabled')) {
            $schema->table('oauth_connect_clients', function (Blueprint $table) {
                $table->boolean('oidc_enabled')->default(false)->after('access_policy');
            });
        }

        if (! $schema->hasColumn('oauth_connect_authorization_codes', 'nonce')) {
            $schema->table('oauth_connect_authorization_codes', function (Blueprint $table) {
                $table->string('nonce', 255)->nullable()->after('scope');
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('oauth_connect_authorization_codes', 'nonce')) {
            $schema->table('oauth_connect_authorization_codes', function (Blueprint $table) {
                $table->dropColumn('nonce');
            });
        }

        if ($schema->hasColumn('oauth_connect_clients', 'oidc_enabled')) {
            $schema->table('oauth_connect_clients', function (Blueprint $table) {
                $table->dropColumn('oidc_enabled');
            });
        }
    },
];
