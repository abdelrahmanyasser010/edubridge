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

class RbacDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private User $teacherUser;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-rbac-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-rbac-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
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

    public function test_dashboard_admin_can_manage_roles_matrix_and_admin_accounts(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-rbac-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/rbac/roles')
            ->assertOk()
            ->assertJsonFragment(['key' => 'school_admin']);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/rbac/matrix')
            ->assertOk()
            ->assertJsonFragment(['rbac.manage' => true]);

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/rbac/roles', [
                'key' => 'custom_operations',
                'name' => 'Custom Operations',
                'permissions' => ['people.view', 'audit.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'custom_operations')
            ->assertJsonPath('data.permissions.0', 'audit.view');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/rbac/matrix', [
                'roles' => [
                    ['key' => 'custom_operations', 'permissions' => ['people.view']],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['people.view' => true]);

        $accountId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/admin-accounts', [
                'name' => 'Finance Admin',
                'email' => 'finance-admin@example.test',
                'password' => 'secret-password',
                'role_key' => 'finance_officer',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role_key', 'finance_officer')
            ->json('data.id');

        $this->assertIsString($accountId);
        $this->assertDatabaseHas('school_user', ['school_id' => $this->school->id, 'role_key' => 'finance_officer', 'status' => 'active'], 'central');
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbac.admin_account.created'], 'tenant');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/admin-accounts/'.$accountId.'/role', ['role_key' => 'student_affairs'])
            ->assertOk()
            ->assertJsonPath('data.role_key', 'student_affairs');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/admin-accounts/'.$accountId.'/status', ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/admin-accounts/'.$accountId.'/role', ['role_key' => 'teacher'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_key']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'rbac.matrix.updated'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbac.admin_account.role_updated'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbac.admin_account.status_updated'], 'tenant');
    }

    public function test_dashboard_rbac_requires_dashboard_token_and_permission(): void
    {
        $this->getJson('/api/v1/dashboard/rbac/roles')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-rbac-no-role');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/rbac/roles')
            ->assertForbidden();

        $teacherToken = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->teacherUser->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'dashboard-rbac-teacher',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->withBearerToken((string) $teacherToken)
            ->getJson('/api/v1/dashboard/rbac/roles')
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
        $this->flushHeaders();
        Auth::forgetGuards();
        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'EduBridge Dashboard Test',
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
        Config::set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), [
            'database' => $database,
        ]));
        DB::purge($connection);
    }

    private function seedIdentity(): void
    {
        $this->admin = User::query()->create(['name' => 'RBAC Admin', 'email' => 'dashboard-rbac-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->noRoleUser = User::query()->create(['name' => 'No Role', 'email' => 'dashboard-rbac-no-role@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->teacherUser = User::query()->create(['name' => 'Teacher', 'email' => 'dashboard-rbac-teacher@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $this->school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->admin, 'school_admin'], [$this->noRoleUser, 'school_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'role_key' => $role,
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
