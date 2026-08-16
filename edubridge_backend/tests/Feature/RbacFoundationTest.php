<?php

namespace Tests\Feature;

use App\Auth\PermissionCatalog;
use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use App\Tenancy\TenantContext;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $user;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('rbac-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('rbac-tenant');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedCentralIdentity();
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

    public function test_tenant_rbac_seeder_creates_exact_permissions_and_system_roles(): void
    {
        $this->assertSame(count(PermissionCatalog::keys()), DB::connection('tenant')->table('permissions')->count());
        $this->assertTrue(DB::connection('tenant')->table('permissions')->where('key', 'attendance.submit')->exists());
        $this->assertFalse(DB::connection('tenant')->table('permissions')->where('key', 'school.*')->exists());
        $this->assertTrue(DB::connection('tenant')->table('roles')->where('key', 'teacher')->where('is_system', true)->exists());
    }

    public function test_permission_middleware_denies_by_default_then_allows_assigned_exact_permission_only(): void
    {
        $token = $this->loginAndReturnToken();

        $this->withBearerToken($token)
            ->getJson('/api/v1/_test/permissions/attendance-submit')
            ->assertForbidden();

        $this->assignRole('teacher');
        $token = $this->loginAndReturnToken('device-2');

        $this->withBearerToken($token)
            ->getJson('/api/v1/_test/permissions/attendance-submit')
            ->assertOk()
            ->assertJsonPath('data.allowed', true);

        $this->withBearerToken($token)
            ->getJson('/api/v1/_test/permissions/payment-refund')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->assertFalse(app(TenantContext::class)->active());
    }

    public function test_gate_denies_without_tenant_context_and_allows_only_when_tenant_matches(): void
    {
        $this->assignRole('teacher');
        $token = $this->loginAndReturnToken();
        $tokenId = (int) Str::before($token, '|');
        $accessToken = PersonalAccessToken::query()->findOrFail($tokenId);
        $tenant = app(TenantConnectionResolver::class)->resolveBySchoolId($this->school->id);

        $this->user->withAccessToken($accessToken);

        $this->assertFalse(Gate::forUser($this->user)->allows('attendance.submit'));

        app(TenantConnectionManager::class)->activate($tenant);

        $this->assertTrue(Gate::forUser($this->user)->allows('attendance.submit'));
        $this->assertFalse(Gate::forUser($this->user)->allows('payment.refund'));
    }

    private function assignRole(string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');

        DB::connection('tenant')->table('user_roles')->insert([
            'central_user_id' => $this->user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function loginAndReturnToken(string $deviceId = 'device-1'): string
    {
        $token = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Teacher Phone',
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

    private function seedCentralIdentity(): void
    {
        $this->user = User::query()->create([
            'name' => 'Teacher User',
            'email' => 'teacher@example.test',
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

        DB::connection('central')->table('school_user')->insert([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'role_key' => 'teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
