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

class DashboardGradeEntryEditingTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $academicUser;

    private User $financeUser;

    private int $assessmentId;

    private int $studentId;

    private int $gradeEntryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-grade-edit-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-grade-edit-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->academicUser, 'academic_admin');
        $this->assignRole($this->financeUser, 'finance_officer');
        $this->seedAssessment();
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

    public function test_dashboard_admin_can_edit_grade_entries_before_publish_or_lock(): void
    {
        $this->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', ['entries' => []])->assertUnauthorized();

        $financeToken = $this->loginAndReturnToken($this->financeUser, 'dashboard-grade-edit-finance');
        $this->withBearerToken($financeToken)
            ->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', [
                'entries' => [['student_id' => $this->studentId, 'score' => 44]],
            ])
            ->assertForbidden();

        $academicToken = $this->loginAndReturnToken($this->academicUser, 'dashboard-grade-edit-academic');

        $this->withBearerToken($academicToken)
            ->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', ['entries' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries']);

        $this->withBearerToken($academicToken)
            ->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', [
                'entries' => [['student_id' => $this->studentId, 'score' => 51]],
            ])
            ->assertConflict();

        $this->withBearerToken($academicToken)
            ->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', [
                'entries' => [['student_id' => $this->studentId, 'score' => 47.5, 'note' => 'Reviewed by admin', 'revision' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $this->gradeEntryId)
            ->assertJsonPath('data.0.score', '47.50')
            ->assertJsonPath('data.0.feedback', 'Reviewed by admin')
            ->assertJsonPath('data.0.revision', 2);

        $this->withBearerToken($academicToken)
            ->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', [
                'entries' => [['student_id' => $this->studentId, 'score' => 48, 'revision' => 1]],
            ])
            ->assertConflict();

        DB::connection('tenant')->table('assessments')->where('id', $this->assessmentId)->update(['published_at' => now(), 'updated_at' => now()]);

        $this->withBearerToken($academicToken)
            ->putJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/grades', [
                'entries' => [['student_id' => $this->studentId, 'score' => 49, 'revision' => 2]],
            ])
            ->assertConflict();

        $this->assertDatabaseHas('audit_logs', ['action' => 'dashboard.grade_entries.updated', 'subject_id' => (string) $this->assessmentId], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
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
        $this->academicUser = User::query()->create(['name' => 'Academic Admin', 'email' => 'dashboard-grade-edit-academic@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->financeUser = User::query()->create(['name' => 'Finance', 'email' => 'dashboard-grade-edit-finance@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        foreach ([[$this->academicUser, 'academic_admin'], [$this->financeUser, 'finance_officer']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $user->id, 'role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedAssessment(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId(['name' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'name' => 'Term 1', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId(['name' => 'Math', 'code' => 'MATH', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_level_subject')->insert(['grade_level_id' => $gradeLevelId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId(['employee_number' => 'T-GRADE-EDIT-001', 'full_name' => 'Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId(['academic_term_id' => $termId, 'teacher_id' => $teacherId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-GRADE-EDIT-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->assessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId(['academic_term_id' => $termId, 'allocation_id' => $allocationId, 'title' => 'Midterm', 'type' => 'exam', 'max_score' => 50, 'weight' => 1, 'status' => 'pending_approval', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->gradeEntryId = (int) DB::connection('tenant')->table('grade_entries')->insertGetId(['assessment_id' => $this->assessmentId, 'student_id' => $this->studentId, 'score' => 45, 'feedback' => 'Initial', 'entered_by_teacher_id' => $teacherId, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
