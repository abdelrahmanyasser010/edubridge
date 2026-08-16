<?php

namespace Tests\Feature\Dashboard;

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

class DashboardGradeExportTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $academicUser;

    private User $financeUser;

    private int $assessmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-grade-export-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-grade-export-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->academicUser, 'academic_admin');
        $this->assignRole($this->financeUser, 'finance_officer');
        $this->seedAssessment();
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

    public function test_dashboard_can_queue_and_read_grade_sheet_export(): void
    {
        $this->postJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/exports')->assertUnauthorized();

        $financeToken = $this->loginAndReturnToken($this->financeUser, 'dashboard-grade-export-finance');
        $this->withBearerToken($financeToken)
            ->postJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/exports')
            ->assertForbidden();

        $academicToken = $this->loginAndReturnToken($this->academicUser, 'dashboard-grade-export-academic');
        $exportId = $this->withBearerToken($academicToken)
            ->postJson('/api/v1/dashboard/assessments/'.$this->assessmentId.'/exports')
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.payload.assessment_id', (string) $this->assessmentId)
            ->json('data.export_id');

        $this->assertIsString($exportId);
        $this->assertStringStartsWith('exp_', $exportId);

        $this->withBearerToken($academicToken)
            ->getJson('/api/v1/dashboard/reports/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('data.export_id', $exportId)
            ->assertJsonPath('data.status', 'queued');

        $this->withBearerToken($academicToken)
            ->getJson('/api/v1/dashboard/reports/exports/exp_missing')
            ->assertNotFound();

        $this->assertDatabaseHas('report_exports', ['public_id' => $exportId, 'status' => 'queued'], 'tenant');
        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'report.grade_sheet_export_requested'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'dashboard.grade_sheet_export.requested'], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loginAndReturnToken(User $user, string $deviceId): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Dashboard',
        ])->assertOk()->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer '.$token])->withHeader('Authorization', 'Bearer '.$token);
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
        Config::set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), ['database' => $database]));
        DB::purge($connection);
    }

    private function seedIdentity(): void
    {
        $this->academicUser = User::query()->create(['name' => 'Academic Admin', 'email' => 'dashboard-grade-export-academic@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->financeUser = User::query()->create(['name' => 'Finance', 'email' => 'dashboard-grade-export-finance@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        foreach ([[$this->academicUser, 'academic_admin'], [$this->financeUser, 'finance_officer']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $user->id, 'role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedAssessment(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId(['name' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'name' => 'Term 1', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId(['name' => 'Math', 'code' => 'MATH', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('grade_level_subject')->insert(['grade_level_id' => $gradeLevelId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId(['employee_number' => 'T-GRADE-EXPORT-001', 'full_name' => 'Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId(['academic_term_id' => $termId, 'teacher_id' => $teacherId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->assessmentId = (int) DB::connection('tenant')->table('assessments')->insertGetId(['academic_term_id' => $termId, 'allocation_id' => $allocationId, 'title' => 'Midterm', 'type' => 'exam', 'max_score' => 50, 'weight' => 1, 'status' => 'published', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
