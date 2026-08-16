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

class CoreStudentParentRelationshipsTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private School $school;

    private int $studentId;

    private int $firstParentId;

    private int $secondParentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('core-links-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('core-links-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->seedPeople();
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

    public function test_student_can_have_multiple_guardians_but_only_one_primary(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $primaryLinkId = $this->withBearerToken($token)
            ->postJson('/api/v1/students/'.$this->studentId.'/parents', [
                'parent_id' => $this->firstParentId,
                'relationship' => 'father',
                'is_primary' => true,
                'can_pickup' => true,
                'valid_from' => '2026-08-01',
                'valid_until' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true)
            ->json('data.id');

        $this->assertIsString($primaryLinkId);

        $this->withBearerToken($token)
            ->postJson('/api/v1/students/'.$this->studentId.'/parents', [
                'parent_id' => $this->secondParentId,
                'relationship' => 'mother',
                'is_primary' => false,
                'can_pickup' => true,
                'valid_from' => '2026-08-01',
                'valid_until' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.relationship', 'mother');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/student-parents/'.$primaryLinkId, [
                'can_pickup' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.can_pickup', false);

        $this->withBearerToken($token)
            ->getJson('/api/v1/students/'.$this->studentId.'/parents')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_second_active_primary_guardian_is_rejected(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->attachGuardian($token, $this->firstParentId, true)->assertCreated();

        $this->attachGuardian($token, $this->secondParentId, true)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_primary']);
    }

    public function test_relationship_valid_until_must_be_after_valid_from_and_archive_keeps_row(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->withBearerToken($token)
            ->postJson('/api/v1/students/'.$this->studentId.'/parents', [
                'parent_id' => $this->firstParentId,
                'relationship' => 'father',
                'is_primary' => true,
                'can_pickup' => true,
                'valid_from' => '2026-08-01',
                'valid_until' => '2026-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_until']);

        $linkId = $this->attachGuardian($token, $this->firstParentId, true)->json('data.id');

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/student-parents/'.$linkId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertTrue(DB::connection('tenant')->table('student_parent')->where('id', $linkId)->exists());
    }

    public function test_student_parent_relationships_require_people_permission(): void
    {
        $this->getJson('/api/v1/students/'.$this->studentId.'/parents')
            ->assertUnauthorized();

        $token = $this->loginAndReturnToken($this->noRoleUser, 'no-role-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/students/'.$this->studentId.'/parents')
            ->assertForbidden();
    }

    private function attachGuardian(string $token, int $parentId, bool $primary): TestResponse
    {
        return $this->withBearerToken($token)
            ->postJson('/api/v1/students/'.$this->studentId.'/parents', [
                'parent_id' => $parentId,
                'relationship' => $primary ? 'father' : 'mother',
                'is_primary' => $primary,
                'can_pickup' => true,
                'valid_from' => '2026-08-01',
                'valid_until' => null,
            ]);
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
            'name' => 'People Admin',
            'email' => 'links-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->noRoleUser = User::query()->create([
            'name' => 'No Role',
            'email' => 'links-no-role@example.test',
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

        foreach ([$this->admin, $this->noRoleUser] as $user) {
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

    private function seedPeople(): void
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

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'S-001',
            'full_name' => 'Sara Student',
            'date_of_birth' => '2018-05-10',
            'gender' => 'female',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['First Guardian', 'Second Guardian'] as $name) {
            $id = (int) DB::connection('tenant')->table('parents')->insertGetId([
                'full_name' => $name,
                'phone' => '+201001112223',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! isset($this->firstParentId)) {
                $this->firstParentId = $id;
            } else {
                $this->secondParentId = $id;
            }
        }
    }
}
