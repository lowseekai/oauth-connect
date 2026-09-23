<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('oauth_connect_applications')) {
            $schema->create('oauth_connect_applications', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->unsigned()->index();
                $table->string('name', 120);
                $table->text('description');
                $table->string('homepage_url', 2048)->nullable();
                $table->text('redirect_uris');
                $table->text('requested_scopes');
                $table->text('approved_scopes')->nullable();
                $table->string('status', 32)->default('pending')->index();
                $table->text('application_note')->nullable();
                $table->text('review_note')->nullable();
                $table->text('client_secret_encrypted')->nullable();
                $table->integer('reviewed_by')->unsigned()->nullable()->index();
                $table->dateTime('reviewed_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->index(['user_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }

        if (! $schema->hasColumn('oauth_connect_applications', 'client_secret_encrypted')) {
            $schema->table('oauth_connect_applications', function (Blueprint $table) {
                $table->text('client_secret_encrypted')->nullable();
            });
        }

        if (! $schema->hasTable('oauth_connect_audit_logs')) {
            $schema->create('oauth_connect_audit_logs', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('actor_user_id')->unsigned()->nullable()->index();
                $table->string('action', 80)->index();
                $table->string('target_type', 80);
                $table->string('target_id', 120)->index();
                $table->text('before_data')->nullable();
                $table->text('after_data')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->dateTime('created_at')->nullable()->index();
            });
        }

        $clientColumns = [
            'approval_status' => function (Blueprint $table) {
                $table->string('approval_status', 32)->default('approved')->index();
            },
            'owner_user_id' => function (Blueprint $table) {
                $table->integer('owner_user_id')->unsigned()->nullable()->index();
            },
            'application_id' => function (Blueprint $table) {
                $table->integer('application_id')->unsigned()->nullable()->index();
            },
            'source' => function (Blueprint $table) {
                $table->string('source', 32)->default('legacy')->index();
            },
            'approved_by' => function (Blueprint $table) {
                $table->integer('approved_by')->unsigned()->nullable();
            },
            'approved_at' => function (Blueprint $table) {
                $table->dateTime('approved_at')->nullable();
            },
            'revoked_at' => function (Blueprint $table) {
                $table->dateTime('revoked_at')->nullable()->index();
            },
            'deleted_at' => function (Blueprint $table) {
                $table->dateTime('deleted_at')->nullable()->index();
            },
        ];

        foreach ($clientColumns as $column => $definition) {
            if (! $schema->hasColumn('oauth_connect_clients', $column)) {
                $schema->table('oauth_connect_clients', $definition);
            }
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasTable('oauth_connect_audit_logs')) {
            $schema->drop('oauth_connect_audit_logs');
        }

        if ($schema->hasTable('oauth_connect_applications')) {
            if ($schema->hasColumn('oauth_connect_applications', 'client_secret_encrypted')) {
                $schema->table('oauth_connect_applications', function (Blueprint $table) {
                    $table->dropColumn('client_secret_encrypted');
                });
            }
            $schema->drop('oauth_connect_applications');
        }

        foreach ([
            'approval_status',
            'owner_user_id',
            'application_id',
            'source',
            'approved_by',
            'approved_at',
            'revoked_at',
            'deleted_at',
        ] as $column) {
            if ($schema->hasColumn('oauth_connect_clients', $column)) {
                $schema->table('oauth_connect_clients', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    },
];
