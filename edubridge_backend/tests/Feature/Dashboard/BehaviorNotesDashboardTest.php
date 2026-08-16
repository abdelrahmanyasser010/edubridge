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

class BehaviorNotesDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $noRoleUser;

    private User $teacherUser;

    private School $school;

    private int $sectionId;

    private int $studentId;

    private int $allocationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-behavior-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-behavior-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedSchoolData();
        $this->seedBehaviorNotes();
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

    public function test_dashboard_behavior_notes_list_returns_live_ids_and_requires_dashboard_permission(): void
    {
        $this->getJson('/api/v1/dashboard/behavior-notes')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-behavior-no-role', 'dashboard');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/behavior-notes')
            ->assertForbidden();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-behavior-teacher', 'teacher');
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/dashboard/behavior-notes')
            ->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'dashboard-behavior-admin', 'dashboard');
        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/behavior-notes?status=pending_review&section_id='.$this->sectionId.'&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', '1')
            ->assertJsonPath('data.0.student_id', (string) $this->studentId)
            ->assertJsonPath('data.0.student_name', 'Student One')
            ->assertJsonPath('data.0.section_id', (string) $this->sectionId)
            ->assertJsonPath('data.0.status', 'pending_review')
            ->assertJsonPath('data.0.available_actions.0', 'publish')
            ->assertJsonPath('data.0.available_actions.1', 'reject');

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/behavior-notes?from=2026-07-23&to=2026-07-22&severity=critical')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'severity']);
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

        return $this
            ->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer '.$token])
            ->withHeader('Authorization', 'Bearer '.$token);
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
        $this->adminUser = $this->createUser('Dashboard Behavior Admin', 'dashboard-behavior-admin@example.test');
        $this->noRoleUser = $this->createUser('Dashboard Behavior No Role', 'dashboard-behavior-no-role@example.test');
        $this->teacherUser = $this->createUser('Dashboard Behavior Teacher', 'dashboard-behavior-teacher@example.test');

        $this->school = School::query()->create([
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

        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $this->teacherUser->id,
            'employee_number' => 'T-001',
            'full_name' => 'Teacher One',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => 'Term 1',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $termId,
            'teacher_id' => $teacherId,
            'section_id' => $this->sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'S-001',
            'full_name' => 'Student One',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $this->sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBehaviorNotes(): void
    {
        DB::connection('tenant')->table('behavior_notes')->insert([
            [
                'student_id' => $this->studentId,
                'allocation_id' => $this->allocationId,
                'created_by_teacher_id' => 1,
                'title' => 'Needs review',
                'body' => 'Pending dashboard action.',
                'severity' => 'warning',
                'status' => 'pending_review',
                'published_at' => null,
                'version' => 1,
                'created_at' => '2026-07-22 10:00:00',
                'updated_at' => '2026-07-22 10:00:00',
            ],
            [
                'student_id' => $this->studentId,
                'allocation_id' => $this->allocationId,
                'created_by_teacher_id' => 1,
                'title' => 'Already published',
                'body' => 'Should be hidden by status filter.',
                'severity' => 'info',
                'status' => 'published',
                'published_at' => '2026-07-22 11:00:00',
                'version' => 2,
                'created_at' => '2026-07-22 11:00:00',
                'updated_at' => '2026-07-22 11:00:00',
            ],
        ]);
    }
}
