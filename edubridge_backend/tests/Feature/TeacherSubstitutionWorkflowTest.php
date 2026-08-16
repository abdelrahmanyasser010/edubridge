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

class TeacherSubstitutionWorkflowTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $substituteUser;

    private int $sessionId;

    private int $substituteTeacherId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('substitution-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('substitution-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'school_admin');
        $this->assignRole($this->substituteUser, 'teacher');
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

    public function test_teacher_substitution_is_conflict_checked_notified_and_answered_once(): void
    {
        $adminToken = $this->loginAndReturnToken($this->adminUser, 'sub-admin-device', 'dashboard');

        $substitutionId = $this->withBearerToken($adminToken)
            ->postJson('/api/v1/teacher-substitutions', [
                'teaching_session_id' => $this->sessionId,
                'substitute_teacher_id' => $this->substituteTeacherId,
                'reason' => 'Original teacher is absent',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->assertIsString($substitutionId);
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $substitutionId, 'status' => 'pending'], 'tenant');
        $this->assertDatabaseHas('notifications', ['type' => 'teacher_substitution.assigned'], 'tenant');
        $this->assertDatabaseHas('notification_deliveries', ['central_user_id' => $this->substituteUser->id, 'channel' => 'database'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher_substitution.created', 'subject_id' => $substitutionId], 'tenant');

        $this->withBearerToken($adminToken)
            ->postJson('/api/v1/teacher-substitutions', [
                'teaching_session_id' => $this->sessionId,
                'substitute_teacher_id' => $this->substituteTeacherId,
            ])
            ->assertConflict();

        $teacherToken = $this->loginAndReturnToken($this->substituteUser, 'sub-teacher-device', 'teacher');

        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/teacher/substitutions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $substitutionId);

        $this->withBearerToken($teacherToken)
            ->postJson('/api/v1/teacher/substitutions/'.$substitutionId.'/accept', ['response_note' => 'Accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher_substitution.accepted', 'subject_id' => $substitutionId], 'tenant');

        $this->withBearerToken($teacherToken)
            ->postJson('/api/v1/teacher/substitutions/'.$substitutionId.'/decline')
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
        $this->adminUser = $this->createUser('Admin User', 'sub-admin@example.test');
        $this->substituteUser = $this->createUser('Substitute Teacher', 'sub-teacher@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->adminUser, 'school_admin'], [$this->substituteUser, 'teacher']] as [$user, $role]) {
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
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-10',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => 'Term 1',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-10',
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

        $originalTeacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'employee_number' => 'T-SUB-001',
            'full_name' => 'Original Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->substituteTeacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => $this->substituteUser->id,
            'employee_number' => 'T-SUB-002',
            'full_name' => 'Substitute Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $termId,
            'teacher_id' => $originalTeacherId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slotId = (int) DB::connection('tenant')->table('schedule_slots')->insertGetId([
            'academic_term_id' => $termId,
            'allocation_id' => $allocationId,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'room' => 'A-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sessionId = (int) DB::connection('tenant')->table('teaching_sessions')->insertGetId([
            'schedule_slot_id' => $slotId,
            'allocation_id' => $allocationId,
            'session_date' => '2026-08-03',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
