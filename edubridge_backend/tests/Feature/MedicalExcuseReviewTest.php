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

class MedicalExcuseReviewTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $parentUser;

    private User $otherParentUser;

    private User $reviewerUser;

    private int $studentId;

    private int $fileId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('medical-excuse-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('medical-excuse-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->parentUser, 'parent');
        $this->assignRole($this->otherParentUser, 'parent');
        $this->assignRole($this->reviewerUser, 'school_admin');
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

    public function test_parent_submits_medical_excuse_and_reviewer_approves_it_updating_absence(): void
    {
        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');

        $excuseId = $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/medical-excuses', [
                'file_id' => $this->fileId,
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-03',
                'reason' => 'Medical appointment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->assertIsString($excuseId);

        $reviewerToken = $this->loginAndReturnToken($this->reviewerUser, 'reviewer-device', 'dashboard');

        $this->withBearerToken($reviewerToken)
            ->postJson('/api/v1/medical-excuses/'.$excuseId.'/approve', [
                'review_note' => 'Approved from submitted report.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('meta.updated_attendance_records', 1);

        $record = DB::connection('tenant')->table('attendance_records')->first();
        $this->assertSame('excused', $record?->status);
        $this->assertSame(2, $record?->revision);
        $this->assertTrue(DB::connection('tenant')->table('audit_logs')->where('action', 'medical_excuse.approved')->exists());
    }

    public function test_reviewer_can_reject_pending_medical_excuse_without_updating_attendance(): void
    {
        $excuseId = $this->createPendingExcuse();
        $reviewerToken = $this->loginAndReturnToken($this->reviewerUser, 'reviewer-device', 'dashboard');

        $this->withBearerToken($reviewerToken)
            ->postJson('/api/v1/medical-excuses/'.$excuseId.'/reject', [
                'review_note' => 'Report date does not match absence.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('absent', DB::connection('tenant')->table('attendance_records')->value('status'));
        $this->assertTrue(DB::connection('tenant')->table('audit_logs')->where('action', 'medical_excuse.rejected')->exists());
    }

    public function test_medical_excuse_submission_requires_student_ownership_and_clean_owned_file(): void
    {
        $otherToken = $this->loginAndReturnToken($this->otherParentUser, 'other-parent-device', 'parent');

        $this->withBearerToken($otherToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/medical-excuses', [
                'file_id' => $this->fileId,
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-03',
            ])
            ->assertNotFound();

        DB::connection('tenant')->table('files')->where('id', $this->fileId)->update(['scan_status' => 'pending']);
        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');

        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/medical-excuses', [
                'file_id' => $this->fileId,
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-03',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file_id']);
    }

    private function createPendingExcuse(): string
    {
        $parentToken = $this->loginAndReturnToken($this->parentUser, 'parent-device', 'parent');
        $excuseId = $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/medical-excuses', [
                'file_id' => $this->fileId,
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-03',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertIsString($excuseId);

        return $excuseId;
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

        $response = $this->postJson('/api/v1/'.$appType.'/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Device',
        ])->assertOk()
            ->assertJsonPath('data.user.id', (string) $user->id);

        $token = $response->json('data.token');

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
        $this->parentUser = $this->createUser('Parent User', 'medical-parent@example.test');
        $this->otherParentUser = $this->createUser('Other Parent', 'medical-other-parent@example.test');
        $this->reviewerUser = $this->createUser('Student Affairs', 'medical-reviewer@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->parentUser, 'parent'], [$this->otherParentUser, 'parent'], [$this->reviewerUser, 'school_admin']] as [$user, $role]) {
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

        $this->fileId = (int) DB::connection('tenant')->table('files')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'owner_central_user_id' => $this->parentUser->id,
            'disk' => 'private',
            'path' => 'tenant/medical/report.pdf',
            'original_name' => 'report.pdf',
            'mime_type' => 'application/pdf',
            'bytes' => 1000,
            'checksum_sha256' => str_repeat('a', 64),
            'scan_status' => 'clean',
            'scanned_at' => now(),
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

        $sessionId = (int) DB::connection('tenant')->table('teaching_sessions')->insertGetId([
            'schedule_slot_id' => $slotId,
            'allocation_id' => $allocationId,
            'session_date' => '2026-08-03',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('attendance_records')->insert([
            'teaching_session_id' => $sessionId,
            'student_id' => $this->studentId,
            'status' => 'absent',
            'recorded_by_teacher_id' => $teacherId,
            'submitted_at' => now(),
            'revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
