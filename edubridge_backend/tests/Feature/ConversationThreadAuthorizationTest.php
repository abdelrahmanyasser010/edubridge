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

class ConversationThreadAuthorizationTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $parentUser;

    private User $teacherUser;

    private User $otherTeacherUser;

    private int $fileId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('conversation-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('conversation-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->parentUser, 'parent');
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

    public function test_parent_can_create_and_list_thread_with_related_teacher_only(): void
    {
        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');

        $threadId = $this->withBearerToken($parentToken)
            ->postJson('/api/v1/conversations', [
                'participant_central_user_id' => $this->teacherUser->id,
                'subject' => 'Homework question',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Homework question')
            ->json('data.id');

        $this->assertIsString($threadId);

        $this->withBearerToken($parentToken)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'teacher-device', 'teacher');

        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $threadId);

        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/conversations', [
                'participant_central_user_id' => $this->otherTeacherUser->id,
                'subject' => 'Not allowed',
            ])
            ->assertConflict();
    }

    public function test_participant_can_send_message_idempotently_with_attachment_and_mark_thread_read(): void
    {
        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');
        $threadId = $this->withBearerToken($parentToken)
            ->postJson('/api/v1/conversations', [
                'participant_central_user_id' => $this->teacherUser->id,
                'subject' => 'Homework question',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertIsString($threadId);

        $this->withBearerToken($parentToken)
            ->withHeader('Idempotency-Key', 'message-1')
            ->postJson('/api/v1/conversations/'.$threadId.'/messages', [
                'body' => 'Can you explain page 3?',
                'attachment_file_ids' => [$this->fileId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Can you explain page 3?')
            ->assertJsonPath('meta.idempotency_replayed', false);

        $this->withBearerToken($parentToken)
            ->withHeader('Idempotency-Key', 'message-1')
            ->postJson('/api/v1/conversations/'.$threadId.'/messages', [
                'body' => 'Can you explain page 3?',
                'attachment_file_ids' => [$this->fileId],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.idempotency_replayed', true);

        $this->assertSame(1, DB::connection('tenant')->table('conversation_messages')->count());
        $this->assertTrue(DB::connection('tenant')->table('notifications')->where('type', 'message.received')->exists());

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'teacher-device', 'teacher');

        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/conversations/'.$threadId.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withBearerToken($teacherToken)
            ->postJson('/api/v1/conversations/'.$threadId.'/read')
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertNotNull(DB::connection('tenant')->table('conversation_participants')->where('central_user_id', $this->teacherUser->id)->value('last_read_at'));
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
        $this->parentUser = $this->createUser('Parent User', 'conversation-parent@example.test');
        $this->teacherUser = $this->createUser('Teacher User', 'conversation-teacher@example.test');
        $this->otherTeacherUser = $this->createUser('Other Teacher', 'conversation-other-teacher@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->parentUser, 'parent'], [$this->teacherUser, 'teacher'], [$this->otherTeacherUser, 'teacher']] as [$user, $role]) {
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

        $sectionId = $this->createSection($gradeLevelId, 'A');
        $otherSectionId = $this->createSection($gradeLevelId, 'B');
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

        $termId = $this->createTerm();
        $teacherId = $this->createTeacher($this->teacherUser->id, 'T-001');
        $otherTeacherId = $this->createTeacher($this->otherTeacherUser->id, 'T-002');
        $this->createAllocation($termId, $teacherId, $sectionId, $subjectId);
        $this->createAllocation($termId, $otherTeacherId, $otherSectionId, $subjectId);
        $this->fileId = $this->createFile($this->parentUser->id);

        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'central_user_id' => $this->parentUser->id,
            'full_name' => 'Parent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'S-001',
            'full_name' => 'Student',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('student_parent')->insert([
            'student_id' => $studentId,
            'parent_id' => $parentId,
            'relationship' => 'mother',
            'is_primary' => true,
            'can_pickup' => true,
            'valid_from' => '2026-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSection(int $gradeLevelId, string $code): int
    {
        return (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => $code,
            'code' => $code,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTerm(): int
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => 'Term 1',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTeacher(int $centralUserId, string $employeeNumber): int
    {
        return (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $centralUserId,
            'employee_number' => $employeeNumber,
            'full_name' => 'Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAllocation(int $termId, int $teacherId, int $sectionId, int $subjectId): void
    {
        DB::connection('tenant')->table('teacher_section_subject')->insert([
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

    private function createFile(int $ownerCentralUserId): int
    {
        return (int) DB::connection('tenant')->table('files')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'owner_central_user_id' => $ownerCentralUserId,
            'disk' => 'private',
            'path' => 'messages/file.txt',
            'original_name' => 'file.txt',
            'mime_type' => 'text/plain',
            'bytes' => 5,
            'checksum_sha256' => hash('sha256', 'file'),
            'scan_status' => 'clean',
            'scanned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
