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

class TeacherAssignmentDraftPublishTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $teacherUser;

    private User $otherTeacherUser;

    private int $allocationId;

    private int $otherAllocationId;

    private int $fileId;

    private int $pendingFileId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('assignment-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('assignment-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->teacherUser, 'teacher');
        $this->assignRole($this->otherTeacherUser, 'teacher');
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

    public function test_teacher_can_create_update_and_publish_assignment_with_outbox_event(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');

        $assignmentId = $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/assignments', [
                'allocation_id' => $this->allocationId,
                'title' => 'Fractions homework',
                'body' => 'Solve page 12.',
                'due_at' => '2026-08-10T10:00:00Z',
                'attachment_file_ids' => [$this->fileId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonCount(1, 'data.attachments')
            ->json('data.id');

        $this->assertIsString($assignmentId);

        $this->withBearerToken($token)
            ->patchJson('/api/v1/teacher/assignments/'.$assignmentId, [
                'title' => 'Updated fractions homework',
                'attachment_file_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated fractions homework')
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(0, 'data.attachments');

        $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/assignments/'.$assignmentId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 3);

        $this->assertTrue(DB::connection('tenant')->table('outbox_messages')->where('event_type', 'assignment.published')->exists());

        $this->withBearerToken($token)
            ->patchJson('/api/v1/teacher/assignments/'.$assignmentId, ['title' => 'Cannot update'])
            ->assertForbidden();
    }

    public function test_teacher_assignment_requires_owned_allocation_and_clean_owned_attachments(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');

        $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/assignments', [
                'allocation_id' => $this->otherAllocationId,
                'title' => 'Wrong allocation',
            ])
            ->assertConflict();

        $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/assignments', [
                'allocation_id' => $this->allocationId,
                'title' => 'Bad attachment',
                'attachment_file_ids' => [$this->pendingFileId],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachment_file_ids']);
    }

    public function test_other_teacher_cannot_update_or_publish_assignment(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');
        $assignmentId = $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/assignments', [
                'allocation_id' => $this->allocationId,
                'title' => 'Private assignment',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertIsString($assignmentId);

        $otherToken = $this->loginAndReturnToken($this->otherTeacherUser, 'other-teacher-device');

        $this->withBearerToken($otherToken)
            ->patchJson('/api/v1/teacher/assignments/'.$assignmentId, ['title' => 'Nope'])
            ->assertForbidden();

        $this->withBearerToken($otherToken)
            ->postJson('/api/v1/teacher/assignments/'.$assignmentId.'/publish')
            ->assertForbidden();
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

    private function loginAndReturnToken(User $user, string $deviceId): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Teacher Phone',
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
            'name' => 'Teacher User',
            'email' => 'assignment-teacher@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->otherTeacherUser = User::query()->create([
            'name' => 'Other Teacher',
            'email' => 'assignment-other-teacher@example.test',
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

        foreach ([$this->teacherUser, $this->otherTeacherUser] as $user) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role_key' => 'teacher',
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

        $otherSectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => 'B',
            'code' => 'B',
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

        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $this->teacherUser->id,
            'employee_number' => 'T-001',
            'full_name' => 'Teacher One',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherTeacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $this->otherTeacherUser->id,
            'employee_number' => 'T-002',
            'full_name' => 'Teacher Two',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->allocationId = $this->createAllocation($termId, $teacherId, $sectionId, $subjectId);
        $this->otherAllocationId = $this->createAllocation($termId, $otherTeacherId, $otherSectionId, $subjectId);

        $this->fileId = $this->createFile($this->teacherUser->id, 'clean', 'assignment/clean.pdf');
        $this->pendingFileId = $this->createFile($this->teacherUser->id, 'pending', 'assignment/pending.pdf');
    }

    private function createAllocation(int $termId, int $teacherId, int $sectionId, int $subjectId): int
    {
        return (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
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
    }

    private function createFile(int $ownerCentralUserId, string $scanStatus, string $path): int
    {
        return (int) DB::connection('tenant')->table('files')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'owner_central_user_id' => $ownerCentralUserId,
            'disk' => 'private',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'application/pdf',
            'bytes' => 1000,
            'checksum_sha256' => hash('sha256', $path),
            'scan_status' => $scanStatus,
            'scanned_at' => $scanStatus === 'clean' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
