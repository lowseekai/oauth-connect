<?php

namespace Lowseekai\OAuthConnect\Tests\Unit;

use Flarum\User\User;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Support\ApplicationDirectorySerializer;
use PHPUnit\Framework\TestCase;

class ApplicationDirectorySerializerTest extends TestCase
{
    public function testDirectoryContainsOnlyPublicApplicationFields(): void
    {
        $owner = new User();
        $owner->username = 'app-owner';

        $application = new Application();
        $application->forceFill([
            'id' => 7,
            'name' => 'Example app',
            'description' => 'Example description',
            'homepage_url' => 'https://example.com',
            'redirect_uris' => '["https://example.com/callback"]',
            'client_secret' => 'must-not-leak',
            'status' => 'approved',
            'created_at' => '2026-09-23 10:00:00',
            'reviewed_at' => '2026-09-23 11:00:00',
        ]);
        $application->setRelation('user', $owner);

        $result = (new ApplicationDirectorySerializer())->serialize($application);

        self::assertSame([
            'id',
            'name',
            'description',
            'homepage_url',
            'username',
            'status',
            'created_at',
            'reviewed_at',
        ], array_keys($result));
        self::assertSame('app-owner', $result['username']);
        self::assertArrayNotHasKey('redirect_uris', $result);
        self::assertArrayNotHasKey('client_secret', $result);
    }
}
