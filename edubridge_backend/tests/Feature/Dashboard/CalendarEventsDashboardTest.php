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

class CalendarEventsDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $noRoleUser;

    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-calendar-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-calendar-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'school_admin');
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

    public function test_dashboard_calendar_events_crud_requires_permission_and_audits_mutations(): void
    {
        $this->getJson('/api/v1/dashboard/calendar/events')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-calendar-no-role', 'dashboard');
        $this->withBearerToken($noRoleToken)->getJson('/api/v1/dashboard/calendar/events')->assertForbidden();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-calendar-teacher', 'teacher');
        $this->withBearerToken($teacherToken)->getJson('/api/v1/dashboard/calendar/events')->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'dashboard-calendar-admin', 'dashboard');
        $eventId = $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/calendar/events', [
                'title' => 'Parent meeting',
                'description' => 'Term opening meeting.',
                'type' => 'meeting',
                'starts_at' => '2026-09-10T08:00:00Z',
                'ends_at' => '2026-09-10T09:00:00Z',
                'all_day' => false,
                'audience_type' => 'all',
                'location' => 'Main hall',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Parent meeting')
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->assertIsString($eventId);

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/calendar/events?type=meeting&from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $eventId);

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/calendar/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.location', 'Main hall');

        $this->withBearerToken($adminToken)
            ->patchJson('/api/v1/dashboard/calendar/events/'.$eventId, [
                'title' => 'Updated parent meeting',
                'location' => 'Room 2',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated parent meeting')
            ->assertJsonPath('data.location', 'Room 2');

        $this->withBearerToken($adminToken)
            ->deleteJson('/api/v1/dashboard/calendar/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/calendar/events', [
                'title' => '',
                'type' => 'bad',
                'starts_at' => 'not-a-date',
                'audience_type' => 'bad',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type', 'starts_at', 'audience_type']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'calendar.event.created'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'calendar.event.updated'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'calendar.event.cancelled'], 'tenant');
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

    private function loginAndReturnToken(User $user, string $deviceId, string $appType): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();
        $token = $this->postJson('/api/v1/'.$appType.'/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Dashboard Test',
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
        $this->adminUser = $this->createUser('Calendar Admin', 'dashboard-calendar-admin@example.test');
        $this->noRoleUser = $this->createUser('Calendar No Role', 'dashboard-calendar-no-role@example.test');
        $this->teacherUser = $this->createUser('Calendar Teacher', 'dashboard-calendar-teacher@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->adminUser, 'school_admin'], [$this->noRoleUser, 'school_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role_key' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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

    private function createUser(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret-password',
            'status' => 'active',
        ]);
    }
}
