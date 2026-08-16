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

class CoreAdminReadsTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('core-admin-read-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('core-admin-read-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->seedReadModels();
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

    public function test_admin_search_is_scoped_limited_and_returns_minimal_records(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/admin/search?q=Sara&type=all&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('data.0.label', 'Sara Teacher')
            ->assertJsonPath('data.1.label', 'Sara Guardian');
    }

    public function test_dashboard_summary_counts_active_records_only(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.teachers', 1)
            ->assertJsonPath('data.parents', 1)
            ->assertJsonPath('data.students', 1)
            ->assertJsonPath('data.sections', 1);
    }

    public function test_admin_reads_require_people_permission(): void
    {
        $this->getJson('/api/v1/admin/search?q=Sa')->assertUnauthorized();

        $token = $this->loginAndReturnToken($this->noRoleUser, 'no-role-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/admin/dashboard/summary')
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
            'name' => 'Admin Reader',
            'email' => 'admin-reader@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->noRoleUser = User::query()->create([
            'name' => 'No Role',
            'email' => 'admin-reader-no-role@example.test',
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

    private function seedReadModels(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId([
            'name' => 'Grade 1',
            'code' => 'G01',
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('sections')->insert([
            'grade_level_id' => $gradeLevelId,
            'name' => 'A',
            'code' => 'A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('teachers')->insert([
            ['employee_number' => 'T-001', 'full_name' => 'Sara Teacher', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['employee_number' => 'T-002', 'full_name' => 'Archived Teacher', 'status' => 'archived', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('tenant')->table('parents')->insert([
            ['full_name' => 'Sara Guardian', 'phone' => '+201001112223', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['full_name' => 'Archived Guardian', 'phone' => '+201001112224', 'status' => 'archived', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('tenant')->table('students')->insert([
            ['admission_number' => 'S-001', 'full_name' => 'Sara Student', 'grade_level_id' => $gradeLevelId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['admission_number' => 'S-002', 'full_name' => 'Archived Student', 'grade_level_id' => $gradeLevelId, 'status' => 'archived', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
