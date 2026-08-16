<?php

namespace Tests\Feature;

use App\Actions\Notifications\NotificationManager;
use App\Models\School;
use App\Models\User;
use App\Tenancy\Tenant;
use App\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCoreTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $user;

    private int $schoolId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('notification-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('notification-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        DB::disconnect('central');
        DB::purge('central');
        gc_collect_cycles();

        foreach ([$this->centralDatabase, $this->tenantDatabase] as $database) {
            if (is_file($database)) {
                unlink($database);
            }
        }

        parent::tearDown();
    }

    public function test_notification_core_creates_deliveries_outbox_receipts_and_preferences(): void
    {
        $this->activateTenant();
        app(NotificationManager::class)->create(
            type: 'assignment.published',
            title: 'New assignment',
            body: 'Math homework is ready.',
            recipientCentralUserIds: [$this->user->id, $this->user->id],
            data: ['assignment_id' => '1'],
        );
        app(TenantConnectionManager::class)->disconnect();

        $this->assertSame(1, DB::connection('tenant')->table('notifications')->count());
        $this->assertSame(2, DB::connection('tenant')->table('notification_deliveries')->count());
        $this->assertSame(1, DB::connection('tenant')->table('outbox_messages')->where('event_type', 'notification.push_requested')->count());

        $token = $this->loginAndReturnToken();
        $deliveryId = $this->withBearerToken($token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0.id');

        $this->assertIsString($deliveryId);

        $this->withBearerToken($token)
            ->postJson('/api/v1/notifications/'.$deliveryId.'/read')
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/notification-preferences', [
                'type' => 'assignment.published',
                'channel' => 'push',
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->activateTenant();
        app(NotificationManager::class)->create(
            type: 'assignment.published',
            title: 'Second assignment',
            body: null,
            recipientCentralUserIds: [$this->user->id],
        );
        app(TenantConnectionManager::class)->disconnect();

        $this->assertSame(1, DB::connection('tenant')->table('outbox_messages')->where('event_type', 'notification.push_requested')->count());
    }

    private function loginAndReturnToken(): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/parent/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'notification-device',
            'device_name' => 'Mobile',
        ])->assertOk()
            ->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this
            ->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer '.$token])
            ->withHeader('Authorization', 'Bearer '.$token);
    }

    private function activateTenant(): void
    {
        app(TenantConnectionManager::class)->activate(new Tenant(
            schoolId: $this->schoolId,
            driver: 'sqlite',
            database: $this->tenantDatabase,
        ));
    }

    private function sqliteDatabasePath(string $name): string
    {
        $directory = storage_path('framework/testing');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.$name.'-'.Str::ulid().'.sqlite';
        touch($path);

        return $path;
    }

    private function configureSqliteConnection(string $connection, string $database): void
    {
        Config::set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), [
            'database' => $database,
        ]));
        DB::purge($connection);
    }

    private function seedIdentity(): void
    {
        $this->user = User::query()->create([
            'name' => 'Notification User',
            'email' => 'notification-user@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        $this->schoolId = (int) $school->id;

        DB::connection('central')->table('school_user')->insert([
            'school_id' => $school->id,
            'user_id' => $this->user->id,
            'role_key' => 'parent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $school->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabase,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
