<?php

namespace Tests\Feature;

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

class ParentGradeReportTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $parentUser;

    private int $studentId;

    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('parent-report-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('parent-report-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->parentUser, 'parent');
        $this->seedReportData();
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

    public function test_parent_sees_only_published_assessment_report_and_requests_certificate_job(): void
    {
        $token = $this->loginAndReturnToken($this->parentUser);

        $this->withBearerToken($token)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/reports/recent-assessments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published Quiz')
            ->assertJsonPath('data.0.score', 18);

        $this->withBearerToken($token)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/reports/certificate', ['academic_term_id' => $this->termId])
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'certificate.generate_requested'], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loginAndReturnToken(User $user): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/parent/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'parent-report-device',
            'device_name' => 'Mobile',
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
        $this->parentUser = User::query()->create(['name' => 'Parent', 'email' => 'parent-report@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $this->parentUser->id, 'role_key' => 'parent', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedReportData(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId(['name' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'name' => 'Term 1', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId(['name' => 'Math', 'code' => 'MATH', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_level_subject')->insert(['grade_level_id' => $gradeLevelId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId(['employee_number' => 'T-REP-001', 'full_name' => 'Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId(['academic_term_id' => $this->termId, 'teacher_id' => $teacherId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId(['central_user_id' => $this->parentUser->id, 'full_name' => 'Parent', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-REP-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('student_parent')->insert(['student_id' => $this->studentId, 'parent_id' => $parentId, 'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true, 'valid_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $publishedAssessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId(['academic_term_id' => $this->termId, 'allocation_id' => $allocationId, 'title' => 'Published Quiz', 'type' => 'quiz', 'max_score' => 20, 'weight' => 1, 'status' => 'published', 'submitted_at' => now(), 'approved_at' => now(), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $draftAssessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId(['academic_term_id' => $this->termId, 'allocation_id' => $allocationId, 'title' => 'Hidden Quiz', 'type' => 'quiz', 'max_score' => 20, 'weight' => 1, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_entries')->insert([
            ['assessment_id' => $publishedAssessmentId, 'student_id' => $this->studentId, 'score' => 18, 'feedback' => 'Nice', 'entered_by_teacher_id' => $teacherId, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['assessment_id' => $draftAssessmentId, 'student_id' => $this->studentId, 'score' => 1, 'feedback' => null, 'entered_by_teacher_id' => $teacherId, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
