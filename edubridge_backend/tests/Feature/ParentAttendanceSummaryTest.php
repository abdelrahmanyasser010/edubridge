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

class ParentAttendanceSummaryTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $parentUser;

    private User $otherParentUser;

    private User $noRoleUser;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('parent-attendance-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('parent-attendance-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->parentUser, 'parent');
        $this->assignRole($this->otherParentUser, 'parent');
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

    public function test_parent_can_view_owned_student_attendance_with_date_filters_and_summary(): void
    {
        $token = $this->loginAndReturnToken($this->parentUser, 'parent-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/attendance?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.student.id', (string) $this->studentId)
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonPath('data.summary.late', 1)
            ->assertJsonPath('data.summary.excused', 0)
            ->assertJsonCount(3, 'data.records')
            ->assertJsonPath('data.records.0.session_date', '2026-08-05');
    }

    public function test_parent_attendance_hides_students_not_owned_by_parent(): void
    {
        $token = $this->loginAndReturnToken($this->otherParentUser, 'other-parent-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/attendance')
            ->assertNotFound();
    }

    public function test_parent_attendance_requires_attendance_permission(): void
    {
        $token = $this->loginAndReturnToken($this->noRoleUser, 'no-role-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/parent/students/'.$this->studentId.'/attendance')
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
        $token = $this->postJson('/api/v1/parent/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Parent Phone',
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
        $this->parentUser = User::query()->create([
            'name' => 'Parent User',
            'email' => 'parent-attendance@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->otherParentUser = User::query()->create([
            'name' => 'Other Parent',
            'email' => 'other-parent-attendance@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->noRoleUser = User::query()->create([
            'name' => 'No Role Parent',
            'email' => 'no-role-parent-attendance@example.test',
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

        foreach ([$this->parentUser, $this->otherParentUser, $this->noRoleUser] as $user) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role_key' => 'parent',
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
        $sectionId = $this->seedAcademicStructure();

        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'central_user_id' => $this->parentUser->id,
            'full_name' => 'Parent One',
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
            'admission_number' => 'S-001',
            'full_name' => 'Sara Student',
            'grade_level_id' => DB::connection('tenant')->table('sections')->where('id', $sectionId)->value('grade_level_id'),
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

        $subjectId = (int) DB::connection('tenant')->table('subjects')->value('id');
        $termId = (int) DB::connection('tenant')->table('academic_terms')->value('id');

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

        $statuses = [
            '2026-08-03' => 'present',
            '2026-08-04' => 'absent',
            '2026-08-05' => 'late',
            '2026-09-01' => 'excused',
        ];

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

        foreach ($statuses as $date => $status) {
            $sessionId = (int) DB::connection('tenant')->table('teaching_sessions')->insertGetId([
                'schedule_slot_id' => $slotId,
                'allocation_id' => $allocationId,
                'session_date' => $date,
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tenant')->table('attendance_records')->insert([
                'teaching_session_id' => $sessionId,
                'student_id' => $this->studentId,
                'status' => $status,
                'recorded_by_teacher_id' => $teacherId,
                'submitted_at' => now(),
                'revision' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedAcademicStructure(): int
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('academic_terms')->insert([
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

        return (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => 'A',
            'code' => 'A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
