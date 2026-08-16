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

class GradeAppealWorkflowTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $parentUser;

    private User $adminUser;

    private int $gradeEntryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->centralDatabase = $this->sqliteDatabasePath('appeal-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('appeal-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);
        $this->seedIdentity();
        $this->assignRole($this->parentUser, 'parent');
        $this->assignRole($this->adminUser, 'academic_admin');
        $this->seedGradeData();
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

    public function test_parent_creates_one_open_grade_appeal_and_admin_reviews_it_once(): void
    {
        $parentToken = $this->loginAndReturnToken($this->parentUser, 'appeal-parent-device', 'parent');
        $appealId = $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/grade-entries/'.$this->gradeEntryId.'/appeals', ['reason' => 'Please review the score.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $this->assertIsString($appealId);
        $this->assertDatabaseHas('audit_logs', ['action' => 'grade_appeal.created', 'subject_id' => $appealId], 'tenant');

        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/grade-entries/'.$this->gradeEntryId.'/appeals', ['reason' => 'Duplicate'])
            ->assertConflict();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'appeal-admin-device', 'dashboard');
        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/grade-appeals/'.$appealId.'/reject', ['review_note' => 'Score verified.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('audit_logs', ['action' => 'grade_appeal.rejected', 'subject_id' => $appealId], 'tenant');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/grade-appeals/'.$appealId.'/approve')
            ->assertForbidden();
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
        $token = $this->postJson("/api/v1/{$appType}/auth/login", [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
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
        $this->parentUser = User::query()->create(['name' => 'Parent', 'email' => 'appeal-parent@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->adminUser = User::query()->create(['name' => 'Admin', 'email' => 'appeal-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        foreach ([[$this->parentUser, 'parent'], [$this->adminUser, 'academic_admin']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $user->id, 'role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedGradeData(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId(['name' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'name' => 'Term 1', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId(['name' => 'Math', 'code' => 'MATH', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_level_subject')->insert(['grade_level_id' => $gradeLevelId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId(['employee_number' => 'T-APL-001', 'full_name' => 'Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId(['academic_term_id' => $termId, 'teacher_id' => $teacherId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId(['central_user_id' => $this->parentUser->id, 'full_name' => 'Parent', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-APL-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('student_parent')->insert(['student_id' => $studentId, 'parent_id' => $parentId, 'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true, 'valid_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $assessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId(['academic_term_id' => $termId, 'allocation_id' => $allocationId, 'title' => 'Published Quiz', 'type' => 'quiz', 'max_score' => 20, 'weight' => 1, 'status' => 'published', 'submitted_at' => now(), 'approved_at' => now(), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->gradeEntryId = (int) DB::connection('tenant')->table('grade_entries')->insertGetId(['assessment_id' => $assessmentId, 'student_id' => $studentId, 'score' => 18, 'feedback' => null, 'entered_by_teacher_id' => $teacherId, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
