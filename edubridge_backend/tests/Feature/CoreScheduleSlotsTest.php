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

class CoreScheduleSlotsTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private int $termId;

    private int $allocationId;

    private int $conflictingAllocationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('core-schedule-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('core-schedule-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->seedScheduleData();
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

    public function test_admin_can_create_update_list_and_archive_schedule_slot(): void
    {
        $token = $this->loginAndReturnToken($this->admin);
        $slotId = $this->createSlot($token)
            ->assertCreated()
            ->assertJsonPath('data.weekday', 1)
            ->json('data.id');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/schedule-slots/'.$slotId, ['room' => 'B-12'])
            ->assertOk()
            ->assertJsonPath('data.room', 'B-12');

        $this->withBearerToken($token)
            ->getJson('/api/v1/schedule-slots')
            ->assertOk()
            ->assertJsonPath('data.0.id', $slotId);

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/schedule-slots/'.$slotId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_overlapping_teacher_or_section_slot_is_rejected(): void
    {
        $token = $this->loginAndReturnToken($this->admin);
        $this->createSlot($token)->assertCreated();

        $this->withBearerToken($token)
            ->postJson('/api/v1/schedule-slots', $this->slotPayload([
                'allocation_id' => $this->conflictingAllocationId,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertConflict()
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_generate_sessions_is_idempotent(): void
    {
        $token = $this->loginAndReturnToken($this->admin);
        $this->createSlot($token)->assertCreated();

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic-terms/'.$this->termId.'/generate-sessions')
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.total', 2);

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic-terms/'.$this->termId.'/generate-sessions')
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.total', 2);

        $this->assertSame(2, DB::connection('tenant')->table('teaching_sessions')->count());
    }

    private function createSlot(string $token): TestResponse
    {
        return $this->withBearerToken($token)->postJson('/api/v1/schedule-slots', $this->slotPayload());
    }

    /** @param array<string, mixed> $overrides */
    private function slotPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_term_id' => $this->termId,
            'allocation_id' => $this->allocationId,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'room' => 'A-01',
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

    private function loginAndReturnToken(User $user): string
    {
        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'schedule-device',
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
            'name' => 'Schedule Admin',
            'email' => 'schedule-admin@example.test',
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

        DB::connection('central')->table('school_user')->insert([
            'school_id' => $school->id,
            'user_id' => $this->admin->id,
            'role_key' => 'school_admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    private function seedScheduleData(): void
    {
        $yearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-10',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
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

        $secondSectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => 'B',
            'code' => 'B',
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
            'employee_number' => 'T-001',
            'full_name' => 'Mona Teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $this->termId,
            'teacher_id' => $teacherId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->conflictingAllocationId = (int) DB::connection('tenant')->table('teacher_section_subject')->insertGetId([
            'academic_term_id' => $this->termId,
            'teacher_id' => $teacherId,
            'section_id' => $secondSectionId,
            'subject_id' => $subjectId,
            'weekly_quota' => 5,
            'is_homeroom' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
