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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecipientAssignmentSubmissionTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $parentUser;

    private User $otherParentUser;

    private User $studentUser;

    private int $studentId;

    private int $assignmentId;

    private int $expiredAssignmentId;

    private int $attachmentFileId;

    private int $parentSubmissionFileId;

    private int $studentSubmissionFileId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->centralDatabase = $this->sqliteDatabasePath('recipient-assignment-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('recipient-assignment-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->parentUser, 'parent');
        $this->assignRole($this->otherParentUser, 'parent');
        $this->assignRole($this->studentUser, 'student');
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

    public function test_parent_can_list_download_and_submit_assignment_for_owned_student(): void
    {
        $token = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');

        $this->withBearerToken($token)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/assignments')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withBearerToken($token)
            ->get('/api/v1/parent/students/'.$this->studentId.'/assignments/'.$this->assignmentId.'/attachments/'.$this->attachmentFileId.'/download')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->withBearerToken($token)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/assignments/'.$this->assignmentId.'/submissions', [
                'file_id' => $this->parentSubmissionFileId,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.student_id', (string) $this->studentId);

        $this->assertSame(1, DB::connection('tenant')->table('assignment_submissions')->count());
    }

    public function test_student_can_submit_and_resubmit_own_assignment_before_deadline(): void
    {
        $token = $this->loginAndReturnToken($this->studentUser, 'student-device', 'student');

        $this->withBearerToken($token)
            ->getJson('/api/v1/student/assignments')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withBearerToken($token)
            ->postJson('/api/v1/student/assignments/'.$this->assignmentId.'/submissions', [
                'file_id' => $this->studentSubmissionFileId,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $this->withBearerToken($token)
            ->postJson('/api/v1/student/assignments/'.$this->assignmentId.'/submissions', [
                'file_id' => $this->studentSubmissionFileId,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);
    }

    public function test_assignment_submission_enforces_ownership_clean_file_and_deadline(): void
    {
        $otherToken = $this->loginAndReturnToken($this->otherParentUser, 'other-parent-device', 'parent');

        $this->withBearerToken($otherToken)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/assignments')
            ->assertNotFound();

        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');

        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/assignments/'.$this->expiredAssignmentId.'/submissions', [
                'file_id' => $this->parentSubmissionFileId,
            ])
            ->assertConflict();

        DB::connection('tenant')->table('files')->where('id', $this->parentSubmissionFileId)->update(['scan_status' => 'pending']);

        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/assignments/'.$this->assignmentId.'/submissions', [
                'file_id' => $this->parentSubmissionFileId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file_id']);
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
        $this->parentUser = $this->createUser('Parent User', 'recipient-parent@example.test');
        $this->otherParentUser = $this->createUser('Other Parent', 'recipient-other-parent@example.test');
        $this->studentUser = $this->createUser('Student User', 'recipient-student@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->parentUser, 'parent'], [$this->otherParentUser, 'parent'], [$this->studentUser, 'student']] as [$user, $role]) {
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

        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'central_user_id' => $this->parentUser->id,
            'full_name' => 'Parent User',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('parents')->insert([
            'central_user_id' => $this->otherParentUser->id,
            'full_name' => 'Other Parent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'central_user_id' => $this->studentUser->id,
            'admission_number' => 'S-001',
            'full_name' => 'Sara Student',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('student_parent')->insert([
            'student_id' => $this->studentId,
            'parent_id' => $parentId,
            'relationship' => 'mother',
            'is_primary' => true,
            'can_pickup' => true,
            'valid_from' => '2026-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
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

        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
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

        $this->attachmentFileId = $this->createFile(999, 'clean', 'assignment/teacher-note.txt', 'hello');
        $this->parentSubmissionFileId = $this->createFile($this->parentUser->id, 'clean', 'assignment/parent-answer.txt', 'answer');
        $this->studentSubmissionFileId = $this->createFile($this->studentUser->id, 'clean', 'assignment/student-answer.txt', 'answer');

        $this->assignmentId = $this->createAssignment($allocationId, $teacherId, '2026-08-10 10:00:00');
        $this->expiredAssignmentId = $this->createAssignment($allocationId, $teacherId, '2026-07-15 10:00:00');
    }

    private function createAssignment(int $allocationId, int $teacherId, string $dueAt): int
    {
        $assignmentId = (int) DB::connection('tenant')->table('assignments')->insertGetId([
            'allocation_id' => $allocationId,
            'assigned_by_teacher_id' => $teacherId,
            'title' => 'Published assignment',
            'body' => 'Read and solve.',
            'due_at' => $dueAt,
            'status' => 'published',
            'published_at' => now(),
            'version' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('assignment_attachments')->insert([
            'assignment_id' => $assignmentId,
            'file_id' => $this->attachmentFileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $assignmentId;
    }

    private function createFile(int $ownerCentralUserId, string $scanStatus, string $path, string $contents = 'file'): int
    {
        Storage::disk('private')->put($path, $contents);

        return (int) DB::connection('tenant')->table('files')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'owner_central_user_id' => $ownerCentralUserId,
            'disk' => 'private',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'text/plain',
            'bytes' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
            'scan_status' => $scanStatus,
            'scanned_at' => $scanStatus === 'clean' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
