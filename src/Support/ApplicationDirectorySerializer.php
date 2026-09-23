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
            'created_at' => $this->date($application->created_at),
            'reviewed_at' => $this->date($application->reviewed_at),
        ];
    }

    private function date($value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : ($value ?: null);
    }
}
