<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAttendanceRosterDraftTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $teacherUser;

    private User $otherTeacherUser;

    private int $sessionId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('att-roster-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('att-roster-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->teacherUser, 'teacher');
        $this->assignRole($this->otherTeacherUser, 'teacher');
        $this->seedAttendanceData();
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

    public function test_teacher_can_view_only_own_session_roster(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/roster')
            ->assertOk()
            ->assertJsonPath('data.students.0.id', (string) $this->studentId)
            ->assertJsonPath('data.draft', null);
    }

    public function test_teacher_can_save_versioned_attendance_draft(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');

        $payload = [
            'records' => [
                ['student_id' => $this->studentId, 'status' => 'present', 'note' => null],
            ],
        ];

        $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/draft', $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $payload['records'][0]['status'] = 'late';

        $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/draft', $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.records.0.status', 'late');

        $this->withBearerToken($token)
            ->getJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/roster')
            ->assertOk()
            ->assertJsonPath('data.draft.version', 2);
    }

    public function test_other_teacher_cannot_access_session_roster_or_draft(): void
    {
        $token = $this->loginAndReturnToken($this->otherTeacherUser, 'other-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/roster')
            ->assertForbidden();

        $this->withBearerToken($token)
            ->putJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/draft', [
                'records' => [
                    ['student_id' => $this->studentId, 'status' => 'present'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_teacher_can_submit_attendance_idempotently_with_audit(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');
        $payload = [
            'records' => [
                ['student_id' => $this->studentId, 'status' => 'present'],
            ],
        ];

        $this->withBearerToken($token)
            ->withHeader('Idempotency-Key', 'submit-session-1')
            ->postJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/submit', $payload)
            ->assertOk()
            ->assertJsonPath('data.submitted_count', 1)
            ->assertJsonPath('meta.idempotency_replayed', false);

        $this->assertSame(1, DB::connection('tenant')->table('attendance_records')->count());
        $this->assertTrue(DB::connection('tenant')->table('audit_logs')->where('action', 'attendance.submitted')->exists());

        $this->withBearerToken($token)
            ->withHeader('Idempotency-Key', 'submit-session-1')
            ->postJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/submit', $payload)
            ->assertOk()
            ->assertJsonPath('meta.idempotency_replayed', true);

        $this->assertSame(1, DB::connection('tenant')->table('attendance_records')->count());
    }

    public function test_attendance_submit_requires_idempotency_and_complete_roster(): void
    {
        $token = $this->loginAndReturnToken($this->teacherUser, 'teacher-device');

        $this->withBearerToken($token)
            ->postJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/submit', [
                'records' => [
                    ['student_id' => $this->studentId, 'status' => 'present'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['Idempotency-Key']);

        $this->withBearerToken($token)
            ->withHeader('Idempotency-Key', 'submit-incomplete')
            ->postJson('/api/v1/teacher/attendance/sessions/'.$this->sessionId.'/submit', [
                'records' => [
                    ['student_id' => 999999, 'status' => 'present'],
                ],
            ])
            ->assertUnprocessable();
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
        $token = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
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
            'email' => 'teacher-att@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->otherTeacherUser = User::query()->create([
            'name' => 'Other Teacher',
            'email' => 'other-teacher-att@example.test',
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

    private function seedAttendanceData(): void
    {
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
            'full_name' => 'Mona Teacher',
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

        $slotId = (int) DB::connection('tenant')->table('schedule_slots')->insertGetId([
            'academic_term_id' => $termId,
            'allocation_id' => $allocationId,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
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

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'S-001',
            'full_name' => 'Sara Student',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
