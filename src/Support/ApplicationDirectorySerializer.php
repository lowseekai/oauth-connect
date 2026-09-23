<?php

namespace Lowseekai\OAuthConnect\Support;

use Lowseekai\OAuthConnect\Models\Application;

class ApplicationDirectorySerializer
{
    public function serialize(Application $application): array
    {
        return [
            'id' => (int) $application->id,
            'name' => $application->name,
            'description' => $application->description,
            'homepage_url' => $application->homepage_url,
            'username' => $application->user?->username,
            'status' => $application->status,
            'created_at' => $application->created_at?->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
        ];
    }
}
