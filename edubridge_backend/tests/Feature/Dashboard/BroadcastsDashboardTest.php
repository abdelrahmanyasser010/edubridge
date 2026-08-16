<?php

namespace Tests\Feature\Dashboard;

use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BroadcastsDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private User $teacherUser;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-broadcast-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-broadcast-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
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

    public function test_dashboard_admin_can_create_send_cancel_and_track_broadcasts(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-broadcast-device');

        $broadcastId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/broadcasts', [
                'title' => 'Important announcement',
                'body' => 'School will finish early tomorrow.',
                'type' => 'announcement',
                'target' => ['type' => 'roles', 'ids' => ['teacher']],
                'channels' => ['database', 'push'],
                'priority' => 'normal',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reach_count', 1)
            ->json('data.id');

        $this->assertIsString($broadcastId);

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/broadcasts/'.$broadcastId.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.reach_count', 1);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/broadcasts/'.$broadcastId.'/deliveries')
            ->assertOk()
            ->assertJsonPath('data.queued', 1)
            ->assertJsonPath('data.sent', 1);

        $scheduledId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/broadcasts', [
                'title' => 'Scheduled reminder',
                'body' => 'Bring your books.',
                'type' => 'reminder',
                'target' => ['type' => 'all'],
                'channels' => ['database'],
                'scheduled_at' => '2026-07-23T10:00:00Z',
                'priority' => 'low',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->json('data.id');

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/broadcasts/'.$scheduledId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/broadcasts?status=sent')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $broadcastId);

        $this->assertDatabaseHas('audit_logs', ['action' => 'broadcast.created'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'broadcast.sent'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'broadcast.cancelled'], 'tenant');
        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'broadcast.dispatch_due'], 'tenant');
    }

    public function test_dashboard_broadcasts_require_dashboard_token_and_permission(): void
    {
        $this->getJson('/api/v1/dashboard/broadcasts')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-broadcast-no-role');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/broadcasts')
            ->assertForbidden();

        $teacherToken = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->teacherUser->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'dashboard-broadcast-teacher',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->withBearerToken((string) $teacherToken)
            ->getJson('/api/v1/dashboard/broadcasts')
            ->assertForbidden();
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert([
            'central_user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function loginAndReturnToken(User $user, string $deviceId): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();
        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'EduBridge Dashboard Test',
        ])->assertOk()->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer '.$token])->withHeader('Authorization', 'Bearer '.$token);
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
        $this->admin = User::query()->create(['name' => 'Broadcast Admin', 'email' => 'dashboard-broadcast-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->noRoleUser = User::query()->create(['name' => 'No Role', 'email' => 'dashboard-broadcast-no-role@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->teacherUser = User::query()->create(['name' => 'Teacher', 'email' => 'dashboard-broadcast-teacher@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $this->school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->admin, 'school_admin'], [$this->noRoleUser, 'school_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'role_key' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $this->school->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabase,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
