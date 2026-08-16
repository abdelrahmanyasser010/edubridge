<?php

namespace Tests\Feature;

use App\Actions\Analytics\EarlyWarningCalculator;
use App\Models\Student;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EarlyWarningCalculatorTest extends TestCase
{
    private string $tenantDatabase;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('analytics-tenant');
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), ['database' => $this->tenantDatabase]));
        DB::purge('tenant');
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        $this->seedData();
    }

    protected function tearDown(): void
    {
        DB::disconnect('tenant');
        DB::purge('tenant');
        if (is_file($this->tenantDatabase)) {
            unlink($this->tenantDatabase);
        }
        parent::tearDown();
    }

    public function test_early_warning_is_explainable_and_versioned(): void
    {
        $result = app(EarlyWarningCalculator::class)->calculate(Student::query()->findOrFail($this->studentId));

        $this->assertSame(EarlyWarningCalculator::VERSION, $result['version']);
        $this->assertContains('published_grade_below_50_percent', $result['reasons']);
        $this->assertGreaterThanOrEqual(40, $result['score']);
    }

    private function seedData(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId(['name' => 'Math', 'code' => 'MATH', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_level_subject')->insert(['grade_level_id' => $gradeLevelId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId(['employee_number' => 'T-ANA-001', 'full_name' => 'Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId(['academic_term_id' => $this->term($gradeLevelId), 'teacher_id' => $teacherId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-ANA-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $assessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId(['academic_term_id' => 1, 'allocation_id' => $allocationId, 'title' => 'Quiz', 'type' => 'quiz', 'max_score' => 100, 'weight' => 1, 'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_entries')->insert(['assessment_id' => $assessmentId, 'student_id' => $this->studentId, 'score' => 40, 'entered_by_teacher_id' => $teacherId, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function term(int $gradeLevelId): int
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId(['name' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::connection('tenant')->table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'name' => 'Term 1', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
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
}
