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

class CorePeopleProfilesTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private School $school;

    private int $gradeLevelId;

    private int $sectionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('core-people-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('core-people-tenant');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->seedAcademicStructure();
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

    public function test_school_admin_can_crud_people_profiles_and_archive_without_hard_delete(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role.key', 'school_admin')
            ->assertJsonPath('data.permissions.0', 'academic.manage');

        $teacherId = $this->withBearerToken($token)
            ->postJson('/api/v1/teachers', [
                'employee_number' => 'T-001',
                'full_name' => 'Mona Teacher',
                'email' => 'mona@example.test',
                'phone' => '+201001112223',
                'specialization' => 'Mathematics',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.specialization', 'Mathematics')
            ->json('data.id');

        $parentId = $this->withBearerToken($token)
            ->postJson('/api/v1/parents', [
                'full_name' => 'Ali Guardian',
                'email' => 'ali@example.test',
                'phone' => '+201009998887',
                'national_id_last4' => '1234',
            ])
            ->assertCreated()
            ->assertJsonPath('data.national_id_last4', '1234')
            ->json('data.id');

        $studentId = $this->withBearerToken($token)
            ->postJson('/api/v1/students', [
                'admission_number' => 'S-001',
                'full_name' => 'Sara Student',
                'date_of_birth' => '2018-05-10',
                'gender' => 'female',
                'grade_level_id' => $this->gradeLevelId,
                'section_id' => $this->sectionId,
                'parent_ids' => [$parentId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.grade_level_id', (string) $this->gradeLevelId)
            ->assertJsonPath('data.parents.0.id', (string) $parentId)
            ->json('data.id');

        $this->withBearerToken($token)
            ->getJson('/api/v1/teachers/'.$teacherId)
            ->assertOk()
            ->assertJsonPath('data.central_user_id', fn (?string $id): bool => $id !== null)
            ->assertJsonPath('data.assigned_sections', []);

        $this->withBearerToken($token)
            ->getJson('/api/v1/parents/'.$parentId)
            ->assertOk()
            ->assertJsonPath('data.children.0.id', (string) $studentId);

        $this->withBearerToken($token)
            ->getJson('/api/v1/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('data.parents.0.relationship', 'father');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/teachers/'.$teacherId, ['full_name' => 'Mona Updated'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Mona Updated');

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/parents/'.$parentId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertTrue(DB::connection('tenant')->table('parents')->where('id', $parentId)->exists());

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->withBearerToken($token)
            ->getJson('/api/v1/teachers')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Mona Updated');

        $this->withBearerToken($token)
            ->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'archived');
    }

    public function test_people_profile_validation_rejects_invalid_pii_shapes(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'admin-device');

        $this->withBearerToken($token)
            ->postJson('/api/v1/parents', [
                'full_name' => 'Invalid Guardian',
                'email' => 'not-email',
                'phone' => 'abc',
                'national_id_last4' => '12345',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'national_id_last4']);

        $this->withBearerToken($token)
            ->postJson('/api/v1/students', [
                'admission_number' => 'S-002',
                'full_name' => 'Future Student',
                'date_of_birth' => now()->addDay()->toDateString(),
                'gender' => 'unknown',
                'grade_level_id' => $this->gradeLevelId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_of_birth', 'gender']);
    }

    public function test_people_profiles_require_authentication_and_people_permission(): void
    {
        $this->getJson('/api/v1/teachers')
            ->assertUnauthorized();

        $token = $this->loginAndReturnToken($this->noRoleUser, 'no-role-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/teachers')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
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
            'email' => 'people-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->noRoleUser = User::query()->create([
            'name' => 'No Role',
            'email' => 'people-no-role@example.test',
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

    private function seedAcademicStructure(): void
    {
        $this->gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId([
            'name' => 'Grade 1',
            'code' => 'G01',
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $this->gradeLevelId,
            'name' => 'A',
            'code' => 'A',
            'capacity' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
