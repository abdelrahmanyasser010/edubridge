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

class DashboardAssessmentsTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $financeUser;

    private User $teacherUser;

    private int $assessmentId;

    private int $allocationId;

    private int $teacherId;

    private int $sectionId;

    private int $subjectId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-assessments-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-assessments-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'academic_admin');
        $this->assignRole($this->financeUser, 'finance_officer');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedSchoolData();
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

    public function test_dashboard_can_list_and_show_assessments_for_grade_approval(): void
    {
        $this->getJson('/api/v1/dashboard/assessments')->assertUnauthorized();

        $financeToken = $this->loginAndReturnToken($this->financeUser, 'dashboard-assessment-finance', 'dashboard');
        $this->withBearerToken($financeToken)
            ->getJson('/api/v1/dashboard/assessments')
            ->assertForbidden();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-assessment-teacher', 'teacher');
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/dashboard/assessments')
            ->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'dashboard-assessment-admin', 'dashboard');

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/assessments?status=nope')
            ->assertUnprocessable();

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/assessments?status=pending_approval&teacher_id='.$this->teacherId.'&section_id='.$this->sectionId.'&subject_id='.$this->subjectId)
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $this->assessmentId)
            ->assertJsonPath('data.0.allocation_id', (string) $this->allocationId)
            ->assertJsonPath('data.0.teacher.id', (string) $this->teacherId)
            ->assertJsonPath('data.0.teacher.full_name', 'Teacher')
            ->assertJsonPath('data.0.section.id', (string) $this->sectionId)
            ->assertJsonPath('data.0.subject.id', (string) $this->subjectId)
            ->assertJsonPath('data.0.grade_summary.expected_students', 1)
            ->assertJsonPath('data.0.grade_summary.scored_entries', 1)
            ->assertJsonPath('data.0.available_actions.0', 'approve')
            ->assertJsonPath('meta.pagination.total', 1);

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/assessments/'.$this->assessmentId)
            ->assertOk()
            ->assertJsonPath('data.id', (string) $this->assessmentId)
            ->assertJsonPath('data.entries.0.student.id', (string) $this->studentId)
            ->assertJsonPath('data.entries.0.student.full_name', 'Student')
            ->assertJsonPath('data.entries.0.entry.score', 45)
            ->assertJsonPath('data.entries.0.entry.revision', 1);

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/assessments/999999')
            ->assertNotFound();
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
        $this->adminUser = $this->createUser('Academic Admin', 'dashboard-assessment-admin@example.test');
        $this->financeUser = $this->createUser('Finance', 'dashboard-assessment-finance@example.test');
        $this->teacherUser = $this->createUser('Teacher', 'dashboard-assessment-teacher@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->adminUser, 'academic_admin'], [$this->financeUser, 'finance_officer'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
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
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId(['name' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'name' => 'Term 1', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId(['name' => 'Math', 'code' => 'MATH', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_level_subject')->insert(['grade_level_id' => $gradeLevelId, 'subject_id' => $this->subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $this->teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId(['central_user_id' => $this->teacherUser->id, 'employee_number' => 'T-DASH-GRD-001', 'full_name' => 'Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId(['academic_term_id' => $termId, 'teacher_id' => $this->teacherId, 'section_id' => $this->sectionId, 'subject_id' => $this->subjectId, 'weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-DASH-GRD-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $this->sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->assessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId([
            'academic_term_id' => $termId,
            'allocation_id' => $this->allocationId,
            'title' => 'Midterm',
            'type' => 'exam',
            'max_score' => 50,
            'weight' => 1,
            'status' => 'pending_approval',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('grade_entries')->insert([
            'assessment_id' => $this->assessmentId,
            'student_id' => $this->studentId,
            'score' => 45,
            'feedback' => 'Great',
            'entered_by_teacher_id' => $this->teacherId,
            'revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
