<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('oauth_connect_clients', 'access_policy')) {
            return;
        }

        $schema->table('oauth_connect_clients', function (Blueprint $table) {
            $table->text('access_policy')->nullable();
        });
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('oauth_connect_clients', 'access_policy')) {
            return;
        }

        $schema->table('oauth_connect_clients', function (Blueprint $table) {
            $table->dropColumn('access_policy');
        });
    },
];
