<?php

namespace Lowseekai\OAuthConnect\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class AuditLog extends AbstractModel
{
    protected $table = 'oauth_connect_audit_logs';

    public $timestamps = false;

    protected $dates = [
        'created_at',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
