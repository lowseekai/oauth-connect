<?php

namespace Lowseekai\OAuthConnect\Tests\Unit;

use Carbon\Carbon;
use Flarum\User\User;
use Flarum\User\Avatar\DriverInterface as AvatarDriverInterface;
use Flarum\User\DisplayName\DriverInterface as DisplayNameDriverInterface;
use Lowseekai\OAuthConnect\Support\UserInfoBuilder;
use PHPUnit\Framework\TestCase;

class OAuthContractTest extends TestCase
{
    public function testExtensionUsesFlarumTwoAndLowseekaiIdentity(): void
    {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('lowseekai/oauth-connect', $composer['name']);
        self::assertSame('^2.0.0-rc.8', $composer['require']['flarum/core']);
        self::assertArrayHasKey('Lowseekai\\OAuthConnect\\', $composer['autoload']['psr-4']);
    }

    public function testAuthorizationCodeAndRefreshTokenContractsRemainOneTimeAndRotating(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Controllers/TokenController.php');

        self::assertStringContainsString("whereNull('used_at')", file_get_contents(dirname(__DIR__, 2).'/src/Models/AuthorizationCode.php'));
        self::assertStringContainsString('$code->used_at = Carbon::now()', $source);
        self::assertStringContainsString('$oldRefreshToken->revoked_at = Carbon::now()', $source);
    }

    public function testOidcDiscoveryAndJwksContractsAreDeclared(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Support/OpenIdConnect.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/extend.php');

        self::assertStringContainsString("'authorization_endpoint'", $source);
        self::assertStringContainsString("'jwks_uri'", $source);
        self::assertStringContainsString("openid-configuration", $routes);
        self::assertStringContainsString("jwks.json", $routes);
    }

    public function testUserInfoHonorsProfileEmailAndStatsScopes(): void
    {
        User::setDisplayNameDriver(new class implements DisplayNameDriverInterface {
            public function displayName(User $user): string
            {
                return $user->getRawOriginal('display_name') ?: $user->username;
            }
        });
        User::setAvatarDriver(new class implements AvatarDriverInterface {
            public function avatarUrl(User $user): ?string
            {
                return $user->getRawOriginal('avatar_url');
            }

            public function avatarSrcset(User $user): ?string
            {
                return null;
            }
        });

        $user = (new User())->setRawAttributes([
            'id' => 42,
            'username' => 'alice',
            'display_name' => 'Alice',
            'email' => 'alice@example.test',
            'is_email_confirmed' => true,
            'avatar_url' => 'https://example.test/avatar.png',
            'joined_at' => Carbon::parse('2026-01-01 00:00:00'),
            'last_seen_at' => Carbon::parse('2026-01-02 00:00:00'),
            'discussion_count' => 3,
            'comment_count' => 9,
        ], true);

        $payload = (new UserInfoBuilder())->build($user, ['profile', 'email', 'user.stats']);

        self::assertSame('42', $payload['sub']);
        self::assertSame('alice@example.test', $payload['email']);
        self::assertTrue($payload['email_verified']);
        self::assertSame('alice', $payload['preferred_username']);
        self::assertSame(3, $payload['discussion_count']);
        self::assertSame(9, $payload['comment_count']);
    }
}
