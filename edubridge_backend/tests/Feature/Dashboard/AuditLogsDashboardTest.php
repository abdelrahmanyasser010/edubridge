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

class AuditLogsDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $alphaTenantDatabase;

    private string $betaTenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private User $teacherUser;

    private School $alphaSchool;

    private School $betaSchool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-audit-central');
        $this->alphaTenantDatabase = $this->sqliteDatabasePath('dashboard-audit-alpha');
        $this->betaTenantDatabase = $this->sqliteDatabasePath('dashboard-audit-beta');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->alphaTenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        $this->migrateTenant($this->alphaTenantDatabase);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin', $this->alphaTenantDatabase);
        $this->assignRole($this->teacherUser, 'teacher', $this->alphaTenantDatabase);
        $this->seedAlphaAuditLogs();
        $this->seedBetaTenant();
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        DB::disconnect('central');
        DB::purge('central');
        gc_collect_cycles();

        foreach ([$this->centralDatabase, $this->alphaTenantDatabase, $this->betaTenantDatabase] as $database) {
            if (is_file($database)) {
                unlink($database);
            }
        }

        parent::tearDown();
    }

    public function test_dashboard_admin_can_filter_list_and_view_audit_logs(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-audit-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/audit-logs?action=students.update&entity_type=student&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.actor.name', 'Audit Admin')
            ->assertJsonPath('data.0.action', 'students.update')
            ->assertJsonPath('data.0.entity_type', 'student')
            ->assertJsonPath('data.0.entity_id', '20')
            ->assertJsonPath('data.0.before.section_id', '8')
            ->assertJsonPath('data.0.after.api_key', '[redacted]')
            ->assertJsonMissing(['api_key' => 'sk_live_should_not_leak']);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/audit-logs/1')
            ->assertOk()
            ->assertJsonPath('data.id', '1')
            ->assertJsonPath('data.action', 'students.update')
            ->assertJsonPath('data.before.national_id', '[redacted]')
            ->assertJsonPath('data.after.section_id', '9')
            ->assertJsonMissing(['action' => 'beta.only.action']);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/audit-logs?from=2026-07-23&to=2026-07-22&per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'per_page']);
    }

    public function test_dashboard_audit_logs_require_dashboard_token_and_permission(): void
    {
        $this->getJson('/api/v1/dashboard/audit-logs')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-audit-no-role');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/audit-logs')
            ->assertForbidden();

        $teacherToken = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->teacherUser->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'dashboard-audit-teacher',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->withBearerToken((string) $teacherToken)
            ->getJson('/api/v1/dashboard/audit-logs')
            ->assertForbidden();
    }

    private function migrateTenant(string $database): void
    {
        $this->configureSqliteConnection('tenant', $database);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);
    }

    private function assignRole(User $user, string $role, string $database): void
    {
        $this->configureSqliteConnection('tenant', $database);
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
        $this->admin = User::query()->create(['name' => 'Audit Admin', 'email' => 'dashboard-audit-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->noRoleUser = User::query()->create(['name' => 'No Role', 'email' => 'dashboard-audit-no-role@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->teacherUser = User::query()->create(['name' => 'Teacher', 'email' => 'dashboard-audit-teacher@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $this->alphaSchool = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        $this->betaSchool = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'beta',
            'name' => 'Beta School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->admin, 'school_admin'], [$this->noRoleUser, 'school_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $this->alphaSchool->id,
                'user_id' => $user->id,
                'role_key' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('tenant_connections')->insert([
            [
                'school_id' => $this->alphaSchool->id,
                'driver' => 'sqlite',
                'database' => $this->alphaTenantDatabase,
                'status' => 'active',
                'migrated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->betaSchool->id,
                'driver' => 'sqlite',
                'database' => $this->betaTenantDatabase,
                'status' => 'active',
                'migrated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedAlphaAuditLogs(): void
    {
        $this->configureSqliteConnection('tenant', $this->alphaTenantDatabase);

        DB::connection('tenant')->table('audit_logs')->insert([
            [
                'actor_central_user_id' => $this->admin->id,
                'action' => 'students.update',
                'subject_type' => 'student',
                'subject_id' => '20',
                'before' => json_encode(['section_id' => '8', 'national_id' => '12345678901234'], JSON_THROW_ON_ERROR),
                'after' => json_encode(['section_id' => '9', 'api_key' => 'sk_live_should_not_leak'], JSON_THROW_ON_ERROR),
                'ip_address' => '127.0.0.1',
                'request_id' => 'req_alpha_students',
                'created_at' => '2026-07-22 10:30:00',
            ],
            [
                'actor_central_user_id' => $this->admin->id,
                'action' => 'finance.invoice.created',
                'subject_type' => 'finance_invoice',
                'subject_id' => '88',
                'before' => null,
                'after' => json_encode(['total' => '5000.00'], JSON_THROW_ON_ERROR),
                'ip_address' => '127.0.0.1',
                'request_id' => 'req_alpha_invoice',
                'created_at' => '2026-07-21 09:00:00',
            ],
        ]);
    }

    private function seedBetaTenant(): void
    {
        $this->configureSqliteConnection('tenant', $this->betaTenantDatabase);
        DB::connection('tenant')->statement('create table audit_logs (id integer primary key autoincrement, actor_central_user_id integer null, action varchar(128) not null, subject_type varchar(128) null, subject_id varchar(128) null, before text null, after text null, ip_address varchar(64) null, request_id varchar(128) null, created_at datetime not null)');
        DB::connection('tenant')->table('audit_logs')->insert([
            'actor_central_user_id' => null,
            'action' => 'beta.only.action',
            'subject_type' => 'student',
            'subject_id' => '20',
            'before' => null,
            'after' => null,
            'ip_address' => '127.0.0.1',
            'request_id' => 'req_beta',
            'created_at' => '2026-07-22 10:30:00',
        ]);

        $this->configureSqliteConnection('tenant', $this->alphaTenantDatabase);
    }
}
