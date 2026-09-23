<?php

namespace Lowseekai\OAuthConnect\Support;

use Flarum\User\User;

class AuthorizationCenterAccess
{
    public static function assertSubmitApplication(User $actor): void
    {
        // All authenticated users may submit by default; the permission remains available for group policy UI.
        $actor->assertRegistered();
    }

    public static function assert(User $actor, string $permission): void
    {
        $actor->assertPermission($actor->isAdmin() || $actor->hasPermission($permission));
    }
}
