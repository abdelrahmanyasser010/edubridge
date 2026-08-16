<?php

namespace Tests\Feature;

use App\Actions\Rbac\TenantUserRoleSynchronizer;
use App\Auth\PermissionCatalog;
use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantUserRoleSyncTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabaseA;

    private string $tenantDatabaseB;

    private School $schoolA;

    private School $schoolB;

    private User $user;

    private User $secondAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('sync-central');
        $this->tenantDatabaseA = $this->sqliteDatabasePath('sync-tenant-a');
        $this->tenantDatabaseB = $this->sqliteDatabasePath('sync-tenant-b');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabaseA);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);

        // Migrate and seed Tenant A
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        // Migrate and seed Tenant B
        $this->configureSqliteConnection('tenant', $this->tenantDatabaseB);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedCentrals();
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        DB::disconnect('central');
        DB::purge('central');
        gc_collect_cycles();

        foreach ([$this->centralDatabase, $this->tenantDatabaseA, $this->tenantDatabaseB] as $database) {
            if (is_file($database)) {
                @unlink($database);
            }
        }

        parent::tearDown();
    }

    public function test_new_school_admin_creation_syncs_all_permissions_and_grants_access(): void
    {
        $token = $this->loginDashboardUser($this->user, 'device-admin-1', 'school-a');

        // Create new admin via dashboard endpoint
        $newAdminId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/admin-accounts', [
                'name' => 'New School Admin',
                'email' => 'new-admin@example.test',
                'password' => 'secret-password',
                'role_key' => 'school_admin',
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('data.id');

        $newAdmin = User::query()->findOrFail((int) $newAdminId);

        // Verify central membership
        $this->assertDatabaseHas('school_user', [
            'school_id' => $this->schoolA->id,
            'user_id' => $newAdmin->id,
            'role_key' => 'school_admin',
            'status' => 'active',
        ], 'central');

        // Verify tenant user_roles has school_admin role
        $this->activateTenant($this->schoolA);
        $roleId = DB::connection('tenant')->table('roles')->where('key', 'school_admin')->value('id');
        $this->assertDatabaseHas('user_roles', [
            'central_user_id' => $newAdmin->id,
            'role_id' => $roleId,
        ], 'tenant');

        // Login as new admin and verify full permissions on /auth/me
        $newAdminToken = $this->loginDashboardUser($newAdmin, 'device-new-admin', 'school-a');
        $meData = $this->withBearerToken($newAdminToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data');

        $this->assertSame('school_admin', $meData['role']['key']);
        $this->assertCount(count(PermissionCatalog::keys()), $meData['permissions']);
        $this->assertContains('rbac.manage', $meData['permissions']);
        $this->assertContains('academic.manage', $meData['permissions']);
    }

    public function test_changing_school_admin_to_academic_admin_revokes_full_admin_and_grants_only_new_role(): void
    {
        $token = $this->loginDashboardUser($this->user, 'device-admin-1', 'school-a');

        // Create initial school_admin account
        $adminId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/admin-accounts', [
                'name' => 'Transition Admin',
                'email' => 'transition@example.test',
                'password' => 'secret-password',
                'role_key' => 'school_admin',
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('data.id');

        $targetUser = User::query()->findOrFail((int) $adminId);

        // Change role to academic_admin
        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/admin-accounts/'.$adminId.'/role', [
                'role_key' => 'academic_admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.role_key', 'academic_admin');

        // Verify tenant user_roles only contains academic_admin, not school_admin
        $this->activateTenant($this->schoolA);
        $schoolAdminRoleId = DB::connection('tenant')->table('roles')->where('key', 'school_admin')->value('id');
        $academicAdminRoleId = DB::connection('tenant')->table('roles')->where('key', 'academic_admin')->value('id');

        $this->assertDatabaseMissing('user_roles', [
            'central_user_id' => $targetUser->id,
            'role_id' => $schoolAdminRoleId,
        ], 'tenant');

        $this->assertDatabaseHas('user_roles', [
            'central_user_id' => $targetUser->id,
            'role_id' => $academicAdminRoleId,
        ], 'tenant');

        // Login as transitioned user
        $userToken = $this->loginDashboardUser($targetUser, 'device-transition', 'school-a');
        $meData = $this->withBearerToken($userToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data');

        $this->assertSame('academic_admin', $meData['role']['key']);
        $this->assertNotContains('rbac.manage', $meData['permissions']);
        $this->assertNotContains('wallet.limit_manage', $meData['permissions']);
        $this->assertContains('academic.manage', $meData['permissions']);
        $this->assertContains('grade.approve', $meData['permissions']);
    }

    public function test_fail_closed_when_central_role_changes_but_tenant_sync_fails(): void
    {
        $synchronizer = app(TenantUserRoleSynchronizer::class);
        $synchronizer->syncUser($this->schoolA->id, $this->user->id);

        $token = $this->loginDashboardUser($this->user, 'device-fail-closed', 'school-a');

        // User initially has school_admin
        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/rbac/roles')
            ->assertOk();

        // Simulate Central role changed to academic_admin WITHOUT synchronizing Tenant DB
        DB::connection('central')->table('school_user')
            ->where('school_id', $this->schoolA->id)
            ->where('user_id', $this->user->id)
            ->update(['role_key' => 'academic_admin']);

        // Now tenant DB still has school_admin, but central is academic_admin.
        // Fail-closed requires that PermissionChecker immediately denies school_admin permissions!
        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/rbac/roles')
            ->assertForbidden();

        // /auth/me returns 0 permissions due to role mismatch
        $meData = $this->withBearerToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $meData['permissions']);
    }

    public function test_disabling_membership_revokes_tokens_and_removes_all_permissions(): void
    {
        $token = $this->loginDashboardUser($this->user, 'device-admin-1', 'school-a');

        $accountId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/admin-accounts', [
                'name' => 'To Suspend',
                'email' => 'to-suspend@example.test',
                'password' => 'secret-password',
                'role_key' => 'finance_officer',
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('data.id');

        $suspendUser = User::query()->findOrFail((int) $accountId);
        $suspendUserToken = $this->loginDashboardUser($suspendUser, 'device-suspend', 'school-a');

        // Verify active token works
        $this->withBearerToken($suspendUserToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role.key', 'finance_officer');

        // Suspend the admin account
        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/admin-accounts/'.$accountId.'/status', [
                'status' => 'suspended',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        // Verify token was revoked in database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $suspendUser->id,
            'school_id' => $this->schoolA->id,
            'revoked_at' => null,
        ], 'central');

        // Verify tenant user_roles cleared
        $this->activateTenant($this->schoolA);
        $this->assertDatabaseMissing('user_roles', [
            'central_user_id' => $suspendUser->id,
        ], 'tenant');

        // Request with old token is unauthorized
        $this->withBearerToken($suspendUserToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_expired_valid_until_denies_all_permissions(): void
    {
        $synchronizer = app(TenantUserRoleSynchronizer::class);
        $synchronizer->syncUser($this->schoolA->id, $this->user->id);

        $token = $this->loginDashboardUser($this->user, 'device-expire', 'school-a');

        // Set valid_until in the past in central and sync
        DB::connection('central')->table('school_user')
            ->where('school_id', $this->schoolA->id)
            ->where('user_id', $this->user->id)
            ->update(['valid_until' => now()->subDay()]);

        $synchronizer->syncUser($this->schoolA->id, $this->user->id);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/rbac/roles')
            ->assertForbidden();

        $meData = $this->withBearerToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $meData['permissions']);
    }

    public function test_cross_school_isolation_suspending_in_school_a_does_not_affect_school_b(): void
    {
        $synchronizer = app(TenantUserRoleSynchronizer::class);

        // User belongs to School A as school_admin and School B as teacher
        DB::connection('central')->table('school_user')->insert([
            'school_id' => $this->schoolB->id,
            'user_id' => $this->user->id,
            'role_key' => 'teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $synchronizer->syncUser($this->schoolA->id, $this->user->id);
        $synchronizer->syncUser($this->schoolB->id, $this->user->id);

        $tokenA = $this->loginDashboardUser($this->user, 'device-a', 'school-a');
        $tokenB = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret-password',
            'school_code' => 'school-b',
            'device_id' => 'device-b',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        // Suspend user in School A
        DB::connection('central')->table('school_user')
            ->where('school_id', $this->schoolA->id)
            ->where('user_id', $this->user->id)
            ->update(['status' => 'suspended']);

        $synchronizer->syncUser($this->schoolA->id, $this->user->id);
        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $this->user->id)
            ->where('school_id', $this->schoolA->id)
            ->update(['revoked_at' => now()]);

        // School A access is revoked
        $this->withBearerToken($tokenA)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // School B access is fully operational
        $meB = $this->withBearerToken((string) $tokenB)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data');

        $this->assertSame('teacher', $meB['role']['key']);
        $this->assertContains('attendance.submit', $meB['permissions']);
    }

    public function test_unknown_role_key_fails_closed_and_grants_zero_permissions(): void
    {
        $synchronizer = app(TenantUserRoleSynchronizer::class);

        $unknownRoleUser = User::query()->create([
            'name' => 'Unknown Role User',
            'email' => 'unknown-role@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        DB::connection('central')->table('school_user')->insert([
            'school_id' => $this->schoolA->id,
            'user_id' => $unknownRoleUser->id,
            'role_key' => 'non_existent_role_key',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $synchronizer->syncUser($this->schoolA->id, $unknownRoleUser->id);

        $this->activateTenant($this->schoolA);
        $this->assertDatabaseMissing('user_roles', [
            'central_user_id' => $unknownRoleUser->id,
        ], 'tenant');
    }

    public function test_idempotency_running_sync_multiple_times_produces_no_duplicate_rows(): void
    {
        $synchronizer = app(TenantUserRoleSynchronizer::class);

        $synchronizer->syncUser($this->schoolA->id, $this->user->id);
        $synchronizer->syncUser($this->schoolA->id, $this->user->id);
        $synchronizer->syncAllForSchool($this->schoolA->id);
        $synchronizer->syncAllForSchool($this->schoolA->id);

        $this->activateTenant($this->schoolA);
        $count = DB::connection('tenant')->table('user_roles')
            ->where('central_user_id', $this->user->id)
            ->count();

        $this->assertSame(1, $count);
    }

    private function loginDashboardUser(User $user, string $deviceId, string $schoolCode): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => $schoolCode,
            'device_id' => $deviceId,
            'device_name' => 'Test Device',
        ])->assertOk()->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer '.$token])
            ->withHeader('Authorization', 'Bearer '.$token);
    }

    private function activateTenant(School $school): void
    {
        $tenant = app(TenantConnectionResolver::class)->resolveBySchoolId($school->id);
        app(TenantConnectionManager::class)->activate($tenant);
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

    private function seedCentrals(): void
    {
        $this->user = User::query()->create([
            'name' => 'Primary Admin',
            'email' => 'primary-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->secondAdmin = User::query()->create([
            'name' => 'Secondary Admin',
            'email' => 'secondary-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->schoolA = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'school-a',
            'name' => 'School A',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        $this->schoolB = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'school-b',
            'name' => 'School B',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([$this->user, $this->secondAdmin] as $admin) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $this->schoolA->id,
                'user_id' => $admin->id,
                'role_key' => 'school_admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $this->schoolA->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabaseA,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $this->schoolB->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabaseB,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sync initial roles for school A
        app(TenantUserRoleSynchronizer::class)->syncAllForSchool($this->schoolA->id);
    }
}
