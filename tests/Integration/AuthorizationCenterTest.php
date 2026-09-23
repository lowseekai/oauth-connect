<?php

namespace Lowseekai\OAuthConnect\Tests\Integration;

use Flarum\Foundation\Config;
use Flarum\Settings\UninstalledSettingsRepository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Models\Client;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Lowseekai\OAuthConnect\Support\{AccessPolicy, RandomGenerator, ScopeRegistry, SecretVault, Translation};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class AuthorizationCenterTest extends TestCase
{
    private Manager $db;
    private ApplicationRepository $applications;
    private ClientRepository $clients;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Run PHP with -d extension=pdo_sqlite for database tests.');
        }
        $this->db = new Manager();
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->db->bootEloquent();
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username');
        });
        $this->db->getConnection()->table('users')->insert(['id' => 1, 'username' => 'owner']);
        foreach (glob(dirname(__DIR__, 2).'/migrations/*.php') as $file) {
            $migration = require $file;
            $migration['up']($schema);
        }
        $translation = new Translation(new Translator('en'));
        $scopes = new ScopeRegistry($translation);
        $this->clients = new ClientRepository(new RandomGenerator(), $scopes, new AccessPolicy($translation), $translation);
        $vault = new SecretVault(new Config(['url' => 'https://forum.example', 'oauth_connect' => ['secret_key' => base64_encode(random_bytes(32))]]));
        $this->applications = new ApplicationRepository($this->clients, $scopes, new UninstalledSettingsRepository(), $translation, $this->db->getConnection(), $vault);
    }

    private function submit(): Application
    {
        return $this->applications->create(1, ['name' => 'Example', 'description' => 'Test application', 'redirect_uris' => ['https://app.example/callback']]);
    }

    public function testApprovalCreatesOneClientAndSecretCanOnlyBeClaimedOnce(): void
    {
        $application = $this->submit();
        self::assertSame(0, Client::count());
        [$approved, $client, $secret] = $this->applications->approve($application, 1);
        self::assertSame('approved', $approved->status);
        self::assertTrue(password_verify($secret, $client->client_secret_hash));
        self::assertSame($secret, $this->applications->consumeSecret($approved));
        self::assertNull($this->applications->consumeSecret($approved));
        try {
            $this->applications->approve($application, 1);
            self::fail('Stale approval must be rejected.');
        } catch (\InvalidArgumentException $error) {
            self::assertSame(1, Client::count());
        }
    }

    public function testOwnerLookupDoesNotLeakOtherApplications(): void
    {
        $application = $this->submit();
        self::assertNull($this->applications->findForUser($application->id, 2));
        self::assertNotNull($this->applications->findForUser($application->id, 1));
    }

    public function testWithdrawnApplicationCannotBeApproved(): void
    {
        $application = $this->submit();
        $this->applications->transition($application, 'withdrawn', 1);
        $this->expectException(\InvalidArgumentException::class);
        $this->applications->approve($application, 1);
    }

    public function testRejectedApplicationCanBeReopenedButApprovedApplicationCannotBeReopened(): void
    {
        $application = $this->submit();
        $rejected = $this->applications->transition($application, 'rejected', 1, 'Please clarify the use case.');
        self::assertSame('rejected', $rejected->status);

        $pending = $this->applications->transition($rejected, 'pending', 1, 'Applicant provided more information.');
        self::assertSame('pending', $pending->status);

        [$approved] = $this->applications->approve($pending, 1);
        self::assertSame('approved', $approved->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->applications->transition($approved, 'pending', 1);
    }

    public function testDuplicateActiveApplicationIsRejected(): void
    {
        $this->submit();

        $this->expectException(\InvalidArgumentException::class);
        $this->submit();
    }

    public function testCancelledApplicationCannotBeReopenedOrApproved(): void
    {
        $application = $this->submit();
        $cancelled = $this->applications->transition($application, 'cancelled', 1, 'Cancelled by administrator.');
        self::assertSame('cancelled', $cancelled->status);

        try {
            $this->applications->transition($cancelled, 'pending', 1);
            self::fail('Cancelled applications must remain historical.');
        } catch (\InvalidArgumentException $error) {
            self::assertSame('cancelled', $cancelled->status);
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->applications->approve($cancelled, 1);
    }

    public function testMigrationRetryPreservesClientCredentialsAndEnabledState(): void
    {
        [$client] = $this->clients->create(['name' => 'Legacy', 'redirect_uris' => ['http://legacy.example/callback'], 'is_enabled' => false]);
        $before = $client->fresh()->getRawOriginal();
        $migration = require dirname(__DIR__, 2).'/migrations/2026_09_23_000000_add_authorization_center.php';
        $migration['up']($this->db->getConnection()->getSchemaBuilder());
        self::assertSame($before, $client->fresh()->getRawOriginal());
    }

    public function testAuthorizationCenterMigrationPreservesAClientFromTheLegacySchema(): void
    {
        $legacyDb = new Manager();
        $legacyDb->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $legacyDb->bootEloquent();
        $legacySchema = $legacyDb->getConnection()->getSchemaBuilder();

        foreach (glob(dirname(__DIR__, 2).'/migrations/*.php') as $file) {
            if (basename($file) === '2026_09_23_000000_add_authorization_center.php') {
                continue;
            }

            $migration = require $file;
            $migration['up']($legacySchema);
        }

        $legacyRow = [
            'client_id' => 'legacy-client-id',
            'client_secret_hash' => password_hash('legacy-secret', PASSWORD_DEFAULT),
            'name' => 'Legacy client',
            'description' => 'Must survive migration',
            'homepage_url' => null,
            'icon_url' => null,
            'redirect_uris' => json_encode(['https://legacy.example/callback']),
            'scopes' => 'user.read user.email',
            'access_policy' => null,
            'grant_types' => 'authorization_code refresh_token',
            'is_enabled' => false,
        ];
        $legacyDb->getConnection()->table('oauth_connect_clients')->insert($legacyRow + [
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $before = $legacyDb->getConnection()->table('oauth_connect_clients')->where('client_id', $legacyRow['client_id'])->first();

        $migration = require dirname(__DIR__, 2).'/migrations/2026_09_23_000000_add_authorization_center.php';
        $migration['up']($legacySchema);
        $after = $legacyDb->getConnection()->table('oauth_connect_clients')->where('client_id', $legacyRow['client_id'])->first();

        foreach (['client_id', 'client_secret_hash', 'name', 'description', 'redirect_uris', 'scopes', 'is_enabled'] as $column) {
            self::assertSame($before->{$column}, $after->{$column}, $column.' changed during compatibility migration.');
        }

        self::assertSame('approved', $after->approval_status);
        self::assertSame('legacy', $after->source);
        self::assertNull($after->owner_user_id);
        self::assertNull($after->application_id);
    }
}
