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

class AssessmentGradeEntryWorkflowTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $teacherUser;

    private int $allocationId;

    /** @var list<int> */
    private array $studentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('grades-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('grades-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
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

    public function test_teacher_can_create_assessment_and_bulk_save_grade_entries_with_revisions(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser);

        $assessmentId = $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/assessments', [
                'allocation_id' => $this->allocationId,
                'title' => 'Quiz 1',
                'type' => 'quiz',
                'max_score' => 20,
                'weight' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $this->assertIsString($assessmentId);

        $this->withBearerToken($token)
            ->getJson('/api/v1/teacher/assessments/'.$assessmentId.'/roster')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/assessments/'.$assessmentId.'/grades', [
                'entries' => [
                    ['student_id' => $this->studentIds[0], 'score' => 25],
                ],
            ])
            ->assertConflict();

        $entry = $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/assessments/'.$assessmentId.'/grades', [
                'entries' => [
                    ['student_id' => $this->studentIds[0], 'score' => 18.5, 'feedback' => 'Good work'],
                    ['student_id' => $this->studentIds[1], 'score' => 17],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.revision', 1)
            ->json('data.0');

        $this->assertIsArray($entry);
        $this->assertDatabaseHas('audit_logs', ['action' => 'grade_entries.saved', 'subject_id' => $assessmentId], 'tenant');

        $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/assessments/'.$assessmentId.'/grades', [
                'entries' => [
                    ['student_id' => $this->studentIds[0], 'score' => 19, 'revision' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.revision', 2);

        $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/assessments/'.$assessmentId.'/grades', [
                'entries' => [
                    ['student_id' => $this->studentIds[0], 'score' => 18, 'revision' => 1],
                ],
            ])
            ->assertConflict();
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

    private function loginAndReturnToken(User $user): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'grades-teacher-device',
            'device_name' => 'Mobile',
        ])->assertOk()
            ->assertJsonPath('data.user.id', (string) $user->id)
            ->json('data.token');

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
        $this->teacherUser = User::query()->create([
            'name' => 'Grade Teacher',
            'email' => 'grade-teacher@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        DB::connection('central')->table('school_user')->insert([
            'school_id' => $school->id,
            'user_id' => $this->teacherUser->id,
            'role_key' => 'teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    private function seedSchoolData(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-12-20',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => 'Term 1',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-12-20',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId([
            'name' => 'Grade 1',
            'code' => 'G01',
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
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
            'employee_number' => 'T-GRD-001',
            'full_name' => 'Grade Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $termId,
            'teacher_id' => $teacherId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['Student A', 'Student B'] as $index => $name) {
            $this->studentIds[] = (int) DB::connection('tenant')->table('students')->insertGetId([
                'admission_number' => 'S-GRD-00'.($index + 1),
                'full_name' => $name,
                'grade_level_id' => $gradeLevelId,
                'section_id' => $sectionId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
