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

class SchoolSettingsDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $admin;

    private User $noRoleUser;

    private User $teacherUser;

    private School $school;

    private int $academicYearId;

    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-settings-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-settings-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->admin, 'school_admin');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedAcademic();
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

    public function test_dashboard_admin_can_read_update_settings_and_mask_integrations(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-settings-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/school/settings')
            ->assertOk()
            ->assertJsonPath('data.school.code', 'alpha')
            ->assertJsonPath('data.attendance.late_after_minutes', 10);

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/school/settings', [
                'school' => [
                    'name' => 'Alpha International School',
                    'timezone' => 'Africa/Cairo',
                    'locale' => 'ar',
                    'currency' => 'EGP',
                ],
                'academic' => [
                    'active_academic_year_id' => $this->academicYearId,
                    'active_term_id' => $this->termId,
                ],
                'attendance' => [
                    'late_after_minutes' => 12,
                    'absence_warning_threshold' => 6,
                ],
                'notifications' => [
                    'push_enabled' => true,
                    'sms_enabled' => false,
                    'email_enabled' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.school.name', 'Alpha International School')
            ->assertJsonPath('data.school.currency', 'EGP')
            ->assertJsonPath('data.academic.active_term_id', (string) $this->termId)
            ->assertJsonPath('data.notifications.sms_enabled', false);

        $this->assertDatabaseHas('schools', ['id' => $this->school->id, 'name' => 'Alpha International School', 'currency' => 'EGP'], 'central');
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.settings.updated'], 'tenant');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/school/integrations/sms_gateway', [
                'provider' => 'unifonic',
                'status' => 'connected',
                'api_key' => 'sk_live_1234567890',
                'config' => [
                    'endpoint_url' => 'https://sms.example.test/send',
                    'sender_id' => 'EduBridge',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'sms_gateway')
            ->assertJsonPath('data.masked_api_key', '****-****-7890')
            ->assertJsonMissing(['api_key' => 'sk_live_1234567890']);

        $storedConfig = DB::connection('tenant')->table('integration_settings')->where('key', 'sms_gateway')->value('config');
        $this->assertIsString($storedConfig);
        $this->assertStringNotContainsString('sk_live_1234567890', $storedConfig);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/school/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'sms_gateway')
            ->assertJsonPath('data.0.masked_api_key', '****-****-7890');

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/school/integrations/sms_gateway/test')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->assertDatabaseHas('audit_logs', ['action' => 'school.integration.updated'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.integration.tested'], 'tenant');
    }

    public function test_dashboard_settings_require_dashboard_token_and_permission(): void
    {
        $this->getJson('/api/v1/dashboard/school/settings')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-settings-no-role');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/school/settings')
            ->assertForbidden();

        $teacherToken = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->teacherUser->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'dashboard-settings-teacher',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->withBearerToken((string) $teacherToken)
            ->getJson('/api/v1/dashboard/school/settings')
            ->assertForbidden();
    }

    public function test_dashboard_settings_validation_rejects_invalid_values(): void
    {
        $token = $this->loginAndReturnToken($this->admin, 'dashboard-settings-validation');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/school/settings', [
                'school' => [
                    'timezone' => 'Mars/Olympus',
                    'currency' => 'egp',
                ],
                'attendance' => [
                    'late_after_minutes' => 0,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['school.timezone', 'school.currency', 'attendance.late_after_minutes']);
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
        $this->admin = User::query()->create(['name' => 'Settings Admin', 'email' => 'dashboard-settings-admin@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->noRoleUser = User::query()->create(['name' => 'No Role', 'email' => 'dashboard-settings-no-role@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->teacherUser = User::query()->create(['name' => 'Teacher', 'email' => 'dashboard-settings-teacher@example.test', 'password' => 'secret-password', 'status' => 'active']);

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

    private function seedAcademic(): void
    {
        $this->academicYearId = (int) DB::connection('tenant')->table('academic_years')->insertGetId([
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->termId = (int) DB::connection('tenant')->table('academic_terms')->insertGetId([
            'academic_year_id' => $this->academicYearId,
            'name' => 'Term 1',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-01-15',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
