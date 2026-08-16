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

class TransportDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private User $teacherUser;

    private User $parentUser;

    private int $routeId;

    private int $tripId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-transport-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-transport-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedTransport();
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

    public function test_dashboard_admin_can_read_transport_dashboard_and_mutate_alert_logs(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-transport-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/transport/summary')
            ->assertOk()
            ->assertJsonPath('data.routes', 1)
            ->assertJsonPath('data.on_route', 1)
            ->assertJsonPath('data.assigned_students', 1);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/transport/routes?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.route_name', 'North Route')
            ->assertJsonPath('data.0.plate_number', 'ABC-1234')
            ->assertJsonPath('data.0.status', 'on_route')
            ->assertJsonPath('data.0.last_location.lat', 30.1234567);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/transport/routes/'.$this->routeId.'/passengers')
            ->assertOk()
            ->assertJsonPath('data.0.student_id', (string) $this->studentId)
            ->assertJsonPath('data.0.parent_name', 'Transport Parent');

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/transport/routes/'.$this->routeId.'/delay-alert', [
                'bus_trip_id' => $this->tripId,
                'message' => 'Bus is delayed by 15 minutes.',
                'delay_minutes' => 15,
                'channels' => ['database', 'push'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'delay')
            ->assertJsonPath('data.delay_minutes', 15);

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/transport/routes/'.$this->routeId.'/contact-driver-log', [
                'outcome' => 'called',
                'notes' => 'Driver confirmed traffic delay.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.outcome', 'called')
            ->assertJsonPath('data.driver_phone', '+201001112223');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/transport/routes/'.$this->routeId.'/events')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'contact_driver');

        $this->assertDatabaseHas('notifications', ['type' => 'transport.delay'], 'tenant');
        $this->assertDatabaseHas('notification_deliveries', ['central_user_id' => $this->parentUser->id, 'channel' => 'database'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'transport.delay_alert.created'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'transport.driver_contact.logged'], 'tenant');
    }

    public function test_dashboard_transport_requires_dashboard_token_and_transport_permission(): void
    {
        $this->getJson('/api/v1/dashboard/transport/summary')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-transport-no-role');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/transport/summary')
            ->assertForbidden();

        $teacherToken = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->teacherUser->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'dashboard-transport-teacher',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->withBearerToken((string) $teacherToken)
            ->getJson('/api/v1/dashboard/transport/summary')
            ->assertForbidden();
    }

    public function test_dashboard_transport_validates_delay_alert_payload(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-transport-validation');

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/transport/routes/'.$this->routeId.'/delay-alert', [
                'message' => '',
                'delay_minutes' => 0,
                'channels' => ['fax'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'delay_minutes', 'channels.0']);
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
        $this->admin = User::query()->create(['name' => 'Dashboard Admin', 'email' => 'dashboard-transport-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->noRoleUser = User::query()->create(['name' => 'No Role', 'email' => 'dashboard-transport-no-role@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->teacherUser = User::query()->create(['name' => 'Teacher', 'email' => 'dashboard-transport-teacher@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->parentUser = User::query()->create(['name' => 'Transport Parent', 'email' => 'dashboard-transport-parent@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->admin, 'school_admin'], [$this->noRoleUser, 'school_admin'], [$this->teacherUser, 'teacher'], [$this->parentUser, 'parent']] as [$user, $role]) {
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

    private function seedTransport(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId(['central_user_id' => $this->parentUser->id, 'full_name' => 'Transport Parent', 'phone' => '+201009998887', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'DASH-TRN-001', 'full_name' => 'Transport Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        DB::connection('tenant')->table('student_parent')->insert(['student_id' => $this->studentId, 'parent_id' => $parentId, 'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true, 'valid_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->routeId = (int) DB::connection('tenant')->table('bus_routes')->insertGetId([
            'name' => 'North Route',
            'code' => 'DASH-NORTH',
            'capacity' => 20,
            'driver_name' => 'Driver One',
            'plate_number' => 'ABC-1234',
            'driver_phone' => '+201001112223',
            'supervisor_name' => 'Supervisor One',
            'estimated_arrival_time' => '07:35:00',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('bus_route_assignments')->insert(['bus_route_id' => $this->routeId, 'student_id' => $this->studentId, 'valid_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->tripId = (int) DB::connection('tenant')->table('bus_trips')->insertGetId(['bus_route_id' => $this->routeId, 'service_date' => now()->toDateString(), 'direction' => 'pickup', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('bus_tracking_events')->insert(['bus_trip_id' => $this->tripId, 'latitude' => 30.1234567, 'longitude' => 31.1234567, 'speed_kph' => 35, 'recorded_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
