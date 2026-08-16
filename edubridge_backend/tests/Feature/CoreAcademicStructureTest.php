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

class CoreAcademicStructureTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $viewerWithoutRole;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('core-academic-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('core-academic-tenant');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
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

    public function test_school_admin_can_manage_academic_structure_and_read_aggregate(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $yearId = $this->withBearerToken($token)
            ->postJson('/api/v1/academic-years', [
                'name' => '2026-2027',
                'starts_on' => '2026-08-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', '2026-2027')
            ->json('data.id');

        $this->assertIsString($yearId);

        $termId = $this->withBearerToken($token)
            ->postJson('/api/v1/academic-years/'.$yearId.'/terms', [
                'name' => 'Term 1',
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-12-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic-terms/'.$termId.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $gradeLevelId = $this->withBearerToken($token)
            ->postJson('/api/v1/grade-levels', [
                'name' => 'Grade 1',
                'code' => 'G01',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'G01')
            ->json('data.id');

        $subjectId = $this->withBearerToken($token)
            ->postJson('/api/v1/subjects', [
                'name' => 'Math',
                'code' => 'MATH',
                'grade_level_ids' => [(int) $gradeLevelId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MATH')
            ->json('data.id');

        $sectionId = $this->withBearerToken($token)
            ->postJson('/api/v1/sections', [
                'grade_level_id' => (int) $gradeLevelId,
                'name' => 'A',
                'code' => 'A',
                'capacity' => 30,
            ])
            ->assertCreated()
            ->assertJsonPath('data.capacity', 30)
            ->json('data.id');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/subjects/'.$subjectId, ['name' => 'Mathematics'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mathematics');

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/sections/'.$sectionId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->withBearerToken($token)
            ->getJson('/api/v1/academic/structure')
            ->assertOk()
            ->assertJsonPath('data.academic_years.0.terms.0.status', 'active')
            ->assertJsonPath('data.grade_levels.0.code', 'G01')
            ->assertJsonPath('data.subjects.0.name', 'Mathematics');
    }

    public function test_only_one_term_can_be_active_per_academic_year(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');
        $yearId = $this->createYear($token);
        $firstTermId = $this->createTerm($token, $yearId, 'Term 1', '2026-08-01', '2026-12-31');
        $secondTermId = $this->createTerm($token, $yearId, 'Term 2', '2027-01-01', '2027-06-30');

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic-terms/'.$firstTermId.'/activate')
            ->assertOk();

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic-terms/'.$secondTermId.'/activate')
            ->assertConflict()
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_term_must_stay_inside_academic_year_dates(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');
        $yearId = $this->createYear($token);

        $this->withBearerToken($token)
            ->postJson('/api/v1/academic-years/'.$yearId.'/terms', [
                'name' => 'Too Early',
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-09-01',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['starts_on', 'ends_on']);
    }

    public function test_academic_structure_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/academic/structure')
            ->assertUnauthorized();

        $token = $this->loginAndReturnToken($this->viewerWithoutRole, 'viewer-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/academic/structure')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    private function createYear(string $token): string
    {
        $yearId = $this->withBearerToken($token)
            ->postJson('/api/v1/academic-years', [
                'name' => '2026-2027',
                'starts_on' => '2026-08-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertIsString($yearId);

        return $yearId;
    }

    private function createTerm(string $token, string $yearId, string $name, string $startsOn, string $endsOn): string
    {
        $termId = $this->withBearerToken($token)
            ->postJson('/api/v1/academic-years/'.$yearId.'/terms', [
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertIsString($termId);

        return $termId;
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
            'email' => 'academic-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->viewerWithoutRole = User::query()->create([
            'name' => 'No Role',
            'email' => 'no-role@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([$this->admin, $this->viewerWithoutRole] as $user) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'role_key' => 'school_admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $this->school->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabase,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
