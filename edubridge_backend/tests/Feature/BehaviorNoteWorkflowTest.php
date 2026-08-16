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

class BehaviorNoteWorkflowTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $teacherUser;

    private User $parentUser;

    private User $otherParentUser;

    private User $adminUser;

    private int $studentId;

    private int $allocationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('behavior-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('behavior-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->teacherUser, 'teacher');
        $this->assignRole($this->parentUser, 'parent');
        $this->assignRole($this->otherParentUser, 'parent');
        $this->assignRole($this->adminUser, 'school_admin');
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

    public function test_behavior_note_create_publish_acknowledge_and_resolve_records_timeline_and_audit(): void
    {
        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'teacher-device', 'teacher');

        $noteId = $this->withBearerToken($teacherToken)
            ->postJson('/api/v1/teacher/behavior-notes', [
                'student_id' => $this->studentId,
                'allocation_id' => $this->allocationId,
                'title' => 'Positive participation',
                'body' => 'Student helped classmates.',
                'severity' => 'info',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonCount(1, 'data.timeline')
            ->json('data.id');

        $this->assertIsString($noteId);

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'admin-device', 'dashboard');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/behavior-notes/'.$noteId.'/publish', ['note' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/behavior-notes/'.$noteId.'/recommendations', ['body' => 'Keep encouraging group work.'])
            ->assertCreated()
            ->assertJsonCount(1, 'data.recommendations');

        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');

        $this->withBearerToken($parentToken)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/behavior-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recommendations.0.body', 'Keep encouraging group work.');

        $otherParentToken = $this->loginAndReturnToken($this->otherParentUser, 'other-parent-device', 'parent');

        $this->withBearerToken($otherParentToken)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/behavior-notes')
            ->assertNotFound();

        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/behavior-notes/'.$noteId.'/acknowledge', ['note' => 'Seen'])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/behavior-notes/'.$noteId.'/resolve', ['note' => 'Closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonCount(4, 'data.timeline');

        $this->assertSame(4, DB::connection('tenant')->table('behavior_note_timeline')->count());
        $this->assertTrue(DB::connection('tenant')->table('audit_logs')->where('action', 'behavior_note.resolved')->exists());
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
        $this->teacherUser = $this->createUser('Teacher User', 'behavior-teacher@example.test');
        $this->parentUser = $this->createUser('Parent User', 'behavior-parent@example.test');
        $this->otherParentUser = $this->createUser('Other Parent User', 'behavior-other-parent@example.test');
        $this->adminUser = $this->createUser('Admin User', 'behavior-admin@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->teacherUser, 'teacher'], [$this->parentUser, 'parent'], [$this->otherParentUser, 'parent'], [$this->adminUser, 'school_admin']] as [$user, $role]) {
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

        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $this->teacherUser->id,
            'employee_number' => 'T-001',
            'full_name' => 'Teacher',
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
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'central_user_id' => $this->parentUser->id,
            'full_name' => 'Parent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'S-001',
            'full_name' => 'Student',
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
    }
}
