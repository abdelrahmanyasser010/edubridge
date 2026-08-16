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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CoreTeacherSectionSubjectAllocationTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private int $termId;

    private int $teacherId;

    private int $sectionId;

    private int $subjectId;

    private int $unassignedSubjectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('core-allocation-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('core-allocation-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->seedAllocationData();
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

    public function test_admin_can_create_update_list_and_archive_teacher_section_subject_allocation(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');
        $allocationId = $this->createAllocation($token)
            ->assertCreated()
            ->assertJsonPath('data.weekly_quota', 5)
            ->json('data.id');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/academic/allocations/'.$allocationId, [
                'weekly_quota' => 6,
            ])
            ->assertOk()
            ->assertJsonPath('data.weekly_quota', 6);

        $this->withBearerToken($token)
            ->getJson('/api/v1/academic/allocations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $allocationId);

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/academic/allocations/'.$allocationId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_duplicate_allocation_and_invalid_quota_are_rejected(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->createAllocation($token)->assertCreated();
        $this->createAllocation($token)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id']);

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic/allocations', $this->allocationPayload(['weekly_quota' => 41]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weekly_quota']);
    }

    public function test_subject_must_belong_to_section_grade_level(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic/allocations', $this->allocationPayload([
                'subject_id' => $this->unassignedSubjectId,
            ]))
            ->assertConflict()
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_allocation_requires_academic_permission(): void
    {
        $this->getJson('/api/v1/academic/allocations')->assertUnauthorized();

        $token = $this->loginAndReturnToken($this->noRoleUser, 'no-role-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/academic/allocations')
            ->assertForbidden();
    }

    private function createAllocation(string $token): TestResponse
    {
        return $this->withBearerToken($token)
            ->postJson('/api/v1/academic/allocations', $this->allocationPayload());
    }

    /** @param array<string, mixed> $overrides */
    private function allocationPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_term_id' => $this->termId,
            'teacher_id' => $this->teacherId,
            'section_id' => $this->sectionId,
            'subject_id' => $this->subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
        ], $overrides);
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
        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Test Phone',
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
        $this->admin = User::query()->create([
            'name' => 'Academic Admin',
            'email' => 'allocation-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->noRoleUser = User::query()->create([
            'name' => 'No Role',
            'email' => 'allocation-no-role@example.test',
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

        foreach ([$this->admin, $this->noRoleUser] as $user) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role_key' => 'school_admin',
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

    private function seedAllocationData(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
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

        $this->sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => 'A',
            'code' => 'A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->subjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId([
            'name' => 'Math',
            'code' => 'MATH',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->unassignedSubjectId = (int) DB::connection('tenant')->table('subjects')->insertGetId([
            'name' => 'Science',
            'code' => 'SCI',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('grade_level_subject')->insert([
            'grade_level_id' => $gradeLevelId,
            'subject_id' => $this->subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->teacherId = (int) DB::connection('tenant')->table('teachers')->insertGetId([
            'employee_number' => 'T-001',
            'full_name' => 'Mona Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
