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

class DashboardCanvasPersistenceTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $adminUser;

    private User $academicUser;

    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-canvas-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-canvas-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->adminUser, 'school_admin');
        $this->assignRole($this->academicUser, 'academic_admin');
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

    public function test_dashboard_canvas_config_can_be_saved_and_read_with_version_guard(): void
    {
        $this->getJson('/api/v1/dashboard/canvas-configs/dashboard-home')->assertUnauthorized();

        $teacherToken = $this->loginAndReturnToken($this->teacherUser, 'dashboard-canvas-teacher', 'teacher');
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/dashboard/canvas-configs/dashboard-home')
            ->assertForbidden();

        $academicToken = $this->loginAndReturnToken($this->academicUser, 'dashboard-canvas-academic', 'dashboard');
        $this->withBearerToken($academicToken)
            ->getJson('/api/v1/dashboard/canvas-configs/dashboard-home')
            ->assertForbidden();

        $adminToken = $this->loginAndReturnToken($this->adminUser, 'dashboard-canvas-admin', 'dashboard');

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/canvas-configs/dashboard-home')
            ->assertOk()
            ->assertJsonPath('data.exists', false)
            ->assertJsonPath('data.key', 'dashboard-home');

        $this->withBearerToken($adminToken)
            ->putJson('/api/v1/dashboard/canvas-configs/Bad_Key', [
                'payload' => ['nodes' => []],
            ])
            ->assertUnprocessable();

        $this->withBearerToken($adminToken)
            ->putJson('/api/v1/dashboard/canvas-configs/dashboard-home', [
                'name' => 'Dashboard Home',
                'payload' => [
                    'nodes' => [['id' => 'summary', 'type' => 'kpi']],
                    'edges' => [],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.key', 'dashboard-home')
            ->assertJsonPath('data.name', 'Dashboard Home')
            ->assertJsonPath('data.payload.nodes.0.id', 'summary')
            ->assertJsonPath('data.version', 1);

        $this->withBearerToken($adminToken)
            ->getJson('/api/v1/dashboard/canvas-configs/dashboard-home')
            ->assertOk()
            ->assertJsonPath('data.payload.nodes.0.type', 'kpi')
            ->assertJsonPath('data.version', 1);

        $this->withBearerToken($adminToken)
            ->putJson('/api/v1/dashboard/canvas-configs/dashboard-home', [
                'expected_version' => 99,
                'payload' => ['nodes' => []],
            ])
            ->assertConflict();

        $this->withBearerToken($adminToken)
            ->putJson('/api/v1/dashboard/canvas-configs/dashboard-home', [
                'expected_version' => 1,
                'payload' => ['nodes' => [['id' => 'calendar', 'type' => 'widget']]],
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.payload.nodes.0.id', 'calendar');

        $this->assertDatabaseHas('dashboard_canvas_configs', ['key' => 'dashboard-home', 'version' => 2], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'dashboard.canvas_config.saved'], 'tenant');
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

    private function loginAndReturnToken(User $user, string $deviceId, string $appType): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/'.$appType.'/auth/login', [
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
        $this->adminUser = $this->createUser('School Admin', 'dashboard-canvas-admin@example.test');
        $this->academicUser = $this->createUser('Academic Admin', 'dashboard-canvas-academic@example.test');
        $this->teacherUser = $this->createUser('Teacher', 'dashboard-canvas-teacher@example.test');

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->adminUser, 'school_admin'], [$this->academicUser, 'academic_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role_key' => $role,
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

    private function createUser(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret-password',
            'status' => 'active',
        ]);
    }
}
