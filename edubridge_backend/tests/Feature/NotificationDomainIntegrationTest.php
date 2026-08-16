<?php

namespace Tests\Feature;

use App\Actions\Assignments\AssignmentManager;
use App\Actions\Attendance\SubmitAttendance;
use App\Models\Assignment;
use App\Models\Teacher;
use App\Models\TeachingSession;
use App\Tenancy\Tenant;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDomainIntegrationTest extends TestCase
{
    private string $tenantDatabase;

    private int $allocationId;

    private int $teacherId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabase = $this->sqliteDatabasePath('notification-domain-tenant');
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);

        app(TenantConnectionManager::class)->activate(new Tenant(1, 'sqlite', $this->tenantDatabase));
        $this->seedSchoolData();
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        DB::disconnect('tenant');
        DB::purge('tenant');
        gc_collect_cycles();

        if (is_file($this->tenantDatabase)) {
            unlink($this->tenantDatabase);
        }

        parent::tearDown();
    }

    public function test_assignment_publish_and_attendance_submit_create_deduped_notifications_after_commit(): void
    {
        $assignment = Assignment::query()->create([
            'allocation_id' => $this->allocationId,
            'assigned_by_teacher_id' => $this->teacherId,
            'title' => 'Homework',
            'status' => 'draft',
            'version' => 1,
        ]);

        app(AssignmentManager::class)->publish($assignment);

        $this->assertTrue(DB::connection('tenant')->table('notifications')->where('type', 'assignment.published')->exists());
        $this->assertSame(4, DB::connection('tenant')->table('notification_deliveries')->whereIn('central_user_id', [501, 601])->count());
        $this->assertTrue(DB::connection('tenant')->table('outbox_messages')->where('event_type', 'notification.push_requested')->exists());

        $session = TeachingSession::query()->create([
            'schedule_slot_id' => $this->createScheduleSlot(),
            'allocation_id' => $this->allocationId,
            'session_date' => '2026-08-03',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'status' => 'scheduled',
        ]);

        app(SubmitAttendance::class)->handle($session, Teacher::query()->findOrFail($this->teacherId), [
            ['student_id' => $this->studentId, 'status' => 'absent'],
        ]);

        $this->assertTrue(DB::connection('tenant')->table('notifications')->where('type', 'attendance.submitted')->exists());
        $this->assertSame(2, DB::connection('tenant')->table('notification_deliveries')->where('central_user_id', 601)->whereIn('channel', ['database', 'push'])->where('notification_id', 2)->count());
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

        $this->teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'central_user_id' => 401,
            'employee_number' => 'T-001',
            'full_name' => 'Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $termId,
            'teacher_id' => $this->teacherId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'central_user_id' => 601,
            'full_name' => 'Parent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'central_user_id' => 501,
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

    private function createScheduleSlot(): int
    {
        return (int) DB::connection('tenant')->table('schedule_slots')->insertGetId([
            'academic_term_id' => DB::connection('tenant')->table('academic_terms')->value('id'),
            'allocation_id' => $this->allocationId,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
}
