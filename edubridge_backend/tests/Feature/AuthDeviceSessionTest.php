<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthDeviceSessionTest extends TestCase
{
    private string $centralDatabase;

    private User $user;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('auth-central');
        Config::set('database.connections.central', array_merge(config('database.connections.sqlite'), [
            'database' => $this->centralDatabase,
        ]));
        DB::purge('central');

        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);

        $this->seedIdentity();
    }

    protected function tearDown(): void
    {
        DB::disconnect('central');
        DB::purge('central');
        gc_collect_cycles();

        if (is_file($this->centralDatabase)) {
            unlink($this->centralDatabase);
        }

        parent::tearDown();
    }

    public function test_login_issues_school_scoped_device_token(): void
    {
        $response = $this->postLogin($this->loginPayload());

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'teacher@example.test')
            ->assertJsonPath('data.school.code', 'alpha')
            ->assertJsonPath('data.device_session.device_id', 'device-1');

        $this->assertIsString($response->json('data.token'));

        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertSame($this->school->id, $token->school_id);
        $this->assertSame('device-1', $token->device_id);
        $this->assertSame('teacher', $token->app_type);
        $this->assertNull($token->revoked_at);
    }

    public function test_me_returns_current_user_school_and_device_session(): void
    {
        $token = $this->loginAndReturnToken();

        $response = $this->withBearerToken($token)->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', (string) $this->user->id)
            ->assertJsonPath('data.school.id', (string) $this->school->id)
            ->assertJsonPath('data.device_session.device_id', 'device-1');
    }

    public function test_logout_revokes_current_device_token(): void
    {
        $token = $this->loginAndReturnToken();

        $this->withBearerToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertNotNull(PersonalAccessToken::query()->firstOrFail()->revoked_at);

        $this->app['auth']->forgetGuards();

        $this->withBearerToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_revoke_another_device_session(): void
    {
        $firstToken = $this->loginAndReturnToken('device-1');
        $secondToken = $this->loginAndReturnToken('device-2');
        $secondSessionId = PersonalAccessToken::query()->where('device_id', 'device-2')->value('id');

        $this->withBearerToken($firstToken)
            ->deleteJson('/api/v1/auth/device-sessions/'.$secondSessionId.'/revoke')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withBearerToken($secondToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withBearerToken($firstToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_teacher_can_login_to_teacher_app(): void
    {
        $payload = $this->loginPayload(deviceId: 'device-teacher');
        $payload['app_type'] = 'teacher';

        $this->postLogin($payload)
            ->assertOk();
    }

    public function test_teacher_is_denied_from_dashboard_app(): void
    {
        $payload = $this->loginPayload(deviceId: 'device-teacher-dash');
        $payload['app_type'] = 'dashboard';

        $this->postLogin($payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'APP_ACCESS_DENIED');

        // Verify no token is created in central DB
        $this->assertFalse(
            PersonalAccessToken::query()->where('device_id', 'device-teacher-dash')->exists()
        );
    }

    public function test_parent_is_denied_from_teacher_app(): void
    {
        // Update user role to parent
        DB::connection('central')->table('school_user')
            ->where('school_id', $this->school->id)
            ->where('user_id', $this->user->id)
            ->update(['role_key' => 'parent']);

        $payload = $this->loginPayload(deviceId: 'device-parent-teacher');
        $payload['app_type'] = 'teacher';

        $this->postLogin($payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'APP_ACCESS_DENIED');

        $this->assertFalse(
            PersonalAccessToken::query()->where('device_id', 'device-parent-teacher')->exists()
        );
    }

    public function test_student_is_denied_from_parent_app(): void
    {
        // Update user role to student
        DB::connection('central')->table('school_user')
            ->where('school_id', $this->school->id)
            ->where('user_id', $this->user->id)
            ->update(['role_key' => 'student']);

        $payload = $this->loginPayload(deviceId: 'device-student-parent');
        $payload['app_type'] = 'parent';

        $this->postLogin($payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'APP_ACCESS_DENIED');

        $this->assertFalse(
            PersonalAccessToken::query()->where('device_id', 'device-student-parent')->exists()
        );
    }

    public function test_dashboard_roles_are_allowed_for_dashboard(): void
    {
        $dashboardRoles = ['school_admin', 'academic_admin', 'student_affairs', 'finance_officer'];

        foreach ($dashboardRoles as $role) {
            DB::connection('central')->table('school_user')
                ->where('school_id', $this->school->id)
                ->where('user_id', $this->user->id)
                ->update(['role_key' => $role]);

            $payload = $this->loginPayload(deviceId: 'device-dash-'.$role);
            $payload['app_type'] = 'dashboard';

            $this->postLogin($payload)
                ->assertOk();
        }
    }

    public function test_existing_token_not_revoked_on_role_check_failure(): void
    {
        // First login successfully as teacher to teacher app
        $token = $this->loginAndReturnToken('device-teacher-keep');

        // Now attempt login as teacher but to dashboard app (should fail)
        $payload = $this->loginPayload(deviceId: 'device-teacher-keep');
        $payload['app_type'] = 'dashboard';

        $this->postLogin($payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'APP_ACCESS_DENIED');

        // Verify the original token is still active (not revoked)
        $tokenModel = PersonalAccessToken::query()->where('device_id', 'device-teacher-keep')->firstOrFail();
        $this->assertNull($tokenModel->revoked_at);
    }

    public function test_login_requires_active_school_membership(): void
    {
        DB::connection('central')->table('school_user')->update(['status' => 'suspended']);

        $this->postLogin($this->loginPayload())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_login_is_rate_limited(): void
    {
        $payload = $this->loginPayload(password: 'wrong-password', deviceId: 'rate-device');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin($payload)->assertUnprocessable();
        }

        $this->postLogin($payload)
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    /**
     * @return array<string, string>
     */
    private function loginPayload(string $password = 'secret-password', string $deviceId = 'device-1'): array
    {
        return [
            'email' => 'teacher@example.test',
            'password' => $password,
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Teacher Phone',
            'app_type' => 'teacher',
        ];
    }

    private function loginAndReturnToken(string $deviceId = 'device-1'): string
    {
        $token = $this->postLogin($this->loginPayload(deviceId: $deviceId))
            ->assertOk()
            ->json('data.token');

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

    private function seedIdentity(): void
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
            'database' => 'tenant-alpha.sqlite',
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function postLogin(array $payload): TestResponse
    {
        $appType = $payload['app_type'] ?? 'teacher';
        unset($payload['app_type']);

        return $this->postJson("/api/v1/{$appType}/auth/login", $payload);
    }
}
