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

class SchedulesDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $noRoleUser;

    private User $teacherUser;

    private int $termId;

    private int $sectionId;

    private int $teacherId;

    private int $allocationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-schedule-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-schedule-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedScheduleData();
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

    public function test_dashboard_schedules_return_live_ids_and_conflict_check_requires_permissions(): void
    {
        $this->getJson('/api/v1/dashboard/schedules')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-schedule-no-role', 'dashboard');
        $this->withBearerToken($noRoleToken)->getJson('/api/v1/dashboard/schedules')->assertForbidden();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-schedule-teacher', 'teacher');
        $this->withBearerToken($teacherToken)->getJson('/api/v1/dashboard/schedules')->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'dashboard-schedule-admin', 'dashboard');
        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/schedules?academic_term_id='.$this->termId.'&section_id='.$this->sectionId.'&from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.schedule_slot_id', '1')
            ->assertJsonPath('data.0.allocation_id', (string) $this->allocationId)
            ->assertJsonPath('data.0.teaching_session_ids.0', '1')
            ->assertJsonPath('data.0.sessions.0.id', '1')
            ->assertJsonPath('data.0.teacher_id', (string) $this->teacherId)
            ->assertJsonPath('data.0.section_id', (string) $this->sectionId);

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/schedules/conflicts/check', [
                'academic_term_id' => $this->termId,
                'allocation_id' => $this->allocationId,
                'weekday' => 1,
                'starts_at' => '09:30',
                'ends_at' => '10:30',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_conflict', true)
            ->assertJsonPath('data.conflicts.0.schedule_slot_id', '1');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/dashboard/schedules/conflicts/check', [
                'academic_term_id' => $this->termId,
                'allocation_id' => $this->allocationId,
                'weekday' => 2,
                'starts_at' => '09:30',
                'ends_at' => '10:30',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_conflict', false);
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
        $this->adminUser = $this->createUser('Schedule Admin', 'dashboard-schedule-admin@example.test');
        $this->noRoleUser = $this->createUser('Schedule No Role', 'dashboard-schedule-no-role@example.test');
        $this->teacherUser = $this->createUser('Schedule Teacher', 'dashboard-schedule-teacher@example.test');

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

    private function seedScheduleData(): void
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
        $subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId([
            'name' => 'Math',
            'code' => 'MATH',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('grade_level_subject')->insert([
            'grade_level_id' => $gradeLevelId,
            'subject_id' => $subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $this->teacherUser->id,
            'employee_number' => 'T-001',
            'full_name' => 'Schedule Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => 'Term 1',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-31',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $this->termId,
            'teacher_id' => $this->teacherId,
            'section_id' => $this->sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('schedule_slots')->insert([
            'id' => 1,
            'academic_term_id' => $this->termId,
            'allocation_id' => $this->allocationId,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'room' => '101',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('teaching_sessions')->insert([
            'id' => 1,
            'schedule_slot_id' => 1,
            'allocation_id' => $this->allocationId,
            'session_date' => '2026-09-07',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
