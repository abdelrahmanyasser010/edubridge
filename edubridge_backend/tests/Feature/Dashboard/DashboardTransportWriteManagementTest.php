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

class DashboardTransportWriteManagementTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $academicUser;

    private User $teacherUser;

    /** @var list<int> */
    private array $studentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-transport-write-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-transport-write-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->assignRole($this->academicUser, 'academic_admin');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedStudents();
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

    public function test_dashboard_admin_can_manage_transport_routes_and_assignments(): void
    {
        $this->postJson('/api/v1/dashboard/transport/routes', [])->assertUnauthorized();

        $academicToken = $this->loginAndReturnToken($this->academicUser, 'dashboard-transport-write-academic', 'dashboard');
        $this->withBearerToken($academicToken)
            ->postJson('/api/v1/dashboard/transport/routes', ['name' => 'No Permission', 'code' => 'NO-PERM', 'capacity' => 10])
            ->assertForbidden();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-transport-write-teacher', 'teacher');
        $this->withBearerToken($teacherToken)
            ->postJson('/api/v1/dashboard/transport/routes', ['name' => 'Wrong App', 'code' => 'WRONG-APP', 'capacity' => 10])
            ->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->admin, 'dashboard-transport-write-admin', 'dashboard');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/transport/routes', ['name' => '', 'code' => 'DASH-WRITE', 'capacity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'capacity']);

        $routeId = $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/transport/routes', [
                'name' => 'Dashboard Route',
                'code' => 'DASH-WRITE',
                'capacity' => 1,
                'driver_name' => 'Driver One',
                'plate_number' => 'XYZ-123',
                'driver_phone' => '+201001112223',
                'supervisor_name' => 'Supervisor One',
                'estimated_arrival_time' => '07:45',
            ])
            ->assertCreated()
            ->assertJsonPath('data.route_name', 'Dashboard Route')
            ->assertJsonPath('data.code', 'DASH-WRITE')
            ->assertJsonPath('data.plate_number', 'XYZ-123')
            ->json('data.id');

        $this->assertIsString($routeId);

        $this->withBearerToken($adminToken)
            ->patchJson('/api/v1/dashboard/transport/routes/'.$routeId, [
                'driver_name' => 'Driver Updated',
                'estimated_arrival_time' => '08:10',
            ])
            ->assertOk()
            ->assertJsonPath('data.driver_name', 'Driver Updated')
            ->assertJsonPath('data.estimated_arrival', '08:10');

        $assignmentId = $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/transport/routes/'.$routeId.'/assignments', [
                'student_id' => $this->studentIds[0],
                'valid_from' => '2026-08-03',
            ])
            ->assertCreated()
            ->assertJsonPath('data.student_id', (string) $this->studentIds[0])
            ->json('data.id');

        $this->assertIsString($assignmentId);

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/transport/routes/'.$routeId.'/assignments', [
                'student_id' => $this->studentIds[1],
                'valid_from' => '2026-08-03',
            ])
            ->assertConflict();

        $this->withBearerToken($adminToken)
            ->patchJson('/api/v1/dashboard/transport/routes/'.$routeId.'/assignments/'.$assignmentId, [
                'valid_until' => '2026-12-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.valid_until', '2026-12-20');

        $this->withBearerToken($adminToken)
            ->deleteJson('/api/v1/dashboard/transport/routes/'.$routeId.'/assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->withBearerToken($adminToken)
            ->deleteJson('/api/v1/dashboard/transport/routes/'.$routeId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('audit_logs', ['action' => 'dashboard.bus_route.created'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'dashboard.bus_route_assignment.archived'], 'tenant');
        $this->assertDatabaseHas('bus_routes', ['id' => (int) $routeId, 'status' => 'archived'], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
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
            'device_name' => 'Dashboard',
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
        Config::set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), ['database' => $database]));
        DB::purge($connection);
    }

    private function seedIdentity(): void
    {
        $this->admin = User::query()->create(['name' => 'School Admin', 'email' => 'dashboard-transport-write-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->academicUser = User::query()->create(['name' => 'Academic Admin', 'email' => 'dashboard-transport-write-academic@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->teacherUser = User::query()->create(['name' => 'Teacher', 'email' => 'dashboard-transport-write-teacher@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        foreach ([[$this->admin, 'school_admin'], [$this->academicUser, 'academic_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $user->id, 'role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedStudents(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['S-DASH-TRN-WRITE-001', 'S-DASH-TRN-WRITE-002'] as $admissionNumber) {
            $this->studentIds[] = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => $admissionNumber, 'full_name' => $admissionNumber, 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
