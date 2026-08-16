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

class LeavePermitsDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $noRoleUser;

    private User $teacherUser;

    private int $sectionId;

    private int $studentId;

    private int $parentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-leave-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-leave-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedSchoolData();
        $this->seedLeavePermits();
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

    public function test_dashboard_leave_permits_list_returns_live_ids_and_requires_permission(): void
    {
        $this->getJson('/api/v1/dashboard/leave-permits')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-leave-no-role', 'dashboard');
        $this->withBearerToken($noRoleToken)->getJson('/api/v1/dashboard/leave-permits')->assertForbidden();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-leave-teacher', 'teacher');
        $this->withBearerToken($teacherToken)->getJson('/api/v1/dashboard/leave-permits')->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'dashboard-leave-admin', 'dashboard');
        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/leave-permits?status=pending&section_id='.$this->sectionId.'&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', '1')
            ->assertJsonPath('data.0.student_id', (string) $this->studentId)
            ->assertJsonPath('data.0.parent_id', (string) $this->parentId)
            ->assertJsonPath('data.0.student_name', 'Leave Student')
            ->assertJsonPath('data.0.parent_name', 'Leave Parent')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.available_actions.0', 'approve')
            ->assertJsonPath('data.0.available_actions.1', 'reject');

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/leave-permits?from=2026-07-23&to=2026-07-22&status=bad')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'status']);
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
        $this->adminUser = $this->createUser('Leave Admin', 'dashboard-leave-admin@example.test');
        $this->noRoleUser = $this->createUser('Leave No Role', 'dashboard-leave-no-role@example.test');
        $this->teacherUser = $this->createUser('Leave Teacher', 'dashboard-leave-teacher@example.test');

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

    private function seedSchoolData(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId([
            'name' => 'Grade 1',
            'code' => 'G01',
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => 'A',
            'code' => 'A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'full_name' => 'Leave Parent',
            'phone' => '0500000001',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'LS-001',
            'full_name' => 'Leave Student',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $this->sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedLeavePermits(): void
    {
        DB::connection('tenant')->table('leave_permits')->insert([
            [
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'reason' => 'Medical appointment',
                'requested_leave_at' => '2026-07-22 12:00:00',
                'status' => 'pending',
                'created_at' => '2026-07-22 10:00:00',
                'updated_at' => '2026-07-22 10:00:00',
            ],
            [
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'reason' => 'Already approved',
                'requested_leave_at' => '2026-07-21 12:00:00',
                'status' => 'approved',
                'created_at' => '2026-07-21 10:00:00',
                'updated_at' => '2026-07-21 10:00:00',
            ],
        ]);
    }
}
