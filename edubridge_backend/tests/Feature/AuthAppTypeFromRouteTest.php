<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @describe AuthAppTypeFromRouteTest
 *
 * This test file verifies:
 * 1. Routing-based login (e.g. POST /api/v1/teacher/auth/login) sets correct app_type without body parameter.
 * 2. Personal Access Tokens are issued with specific abilities (e.g. app:teacher).
 * 3. Enforcing token ability matching active route context (e.g. app:teacher on /teacher/ routes).
 * 4. Tenant database resolution by Hostname / Subdomain (AUTH-003).
 */
class AuthAppTypeFromRouteTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $teacherUser;

    private User $parentUser;

    private User $adminUser;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('auth-route-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('auth-route-tenant');

        Config::set('database.connections.central', array_merge(config('database.connections.sqlite'), [
            'database' => $this->centralDatabase,
        ]));
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), [
            'database' => $this->tenantDatabase,
        ]));

        DB::purge('central');
        DB::purge('tenant');

        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => TenantRbacSeeder::class,
            '--force' => true,
        ]);

        $this->seedIdentity();
    }

    protected function tearDown(): void
    {
        DB::disconnect('central');
        DB::disconnect('tenant');
        DB::purge('central');
        DB::purge('tenant');
        gc_collect_cycles();

        if (is_file($this->centralDatabase)) {
            unlink($this->centralDatabase);
        }
        if (is_file($this->tenantDatabase)) {
            unlink($this->tenantDatabase);
        }

        parent::tearDown();
    }

    /**
     * @describe Login Routing and App Type Detection
     */
    public function test_route_based_login_sets_app_type_correctly(): void
    {
        // 1. Log in to /api/v1/teacher/auth/login
        // Note: we do NOT pass "app_type" in the body
        $response = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'device-teacher',
            'device_name' => 'Teacher Phone',
        ]);

        $response->assertOk();
        $this->assertSame('teacher', $response->json('data.device_session.app_type'));

        // Verify token in database has 'app:teacher' ability
        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertSame(['app:teacher'], $token->abilities);
    }

    /**
     * @describe Role Enforcement
     */
    public function test_route_based_login_enforces_roles(): void
    {
        // 2. Teacher trying to log in as dashboard -> Forbidden
        $response = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'device-teacher',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('code', 'APP_ACCESS_DENIED');
    }

    /**
     * @describe Legacy login endpoint is removed and returns 404 (AUTH-007)
     */
    public function test_legacy_login_returns_404(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'device-legacy',
            'app_type' => 'teacher',
        ])->assertStatus(404);
    }

    /**
     * @describe Token Ability Route Enforcement
     */
    public function test_token_abilities_restrict_route_access(): void
    {
        // Issue teacher token
        $teacherToken = $this->getTeacherToken();

        // 1. Accessing a teacher route with teacher token -> OK (e.g. teacher/attendance/sessions/1/roster)
        // Wait, the router matches it. Let's send a request.
        // We will call an endpoint that exists on the route and verify the auth status.
        // Even if it returns 404 because the session doesn't exist, it should NOT return 403 APP_ACCESS_DENIED.
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/teacher/attendance/sessions/999/roster')
            ->assertStatus(404); // Not Found is fine, means we bypassed the 403 auth!

        // 2. Accessing a dashboard route with teacher token -> Forbidden (403 APP_ACCESS_DENIED)
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/admin/search')
            ->assertForbidden()
            ->assertJsonPath('code', 'APP_ACCESS_DENIED');

        // 3. Accessing a parent route with teacher token -> Forbidden (403 APP_ACCESS_DENIED)
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/parent/students/1/attendance')
            ->assertForbidden()
            ->assertJsonPath('code', 'APP_ACCESS_DENIED');

        // 4. Accessing a shared route with teacher token -> OK (like /notifications, we can expect 200 or 404 etc.)
        // Let's call /api/v1/notifications
        $this->withBearerToken($teacherToken)
            ->getJson('/api/v1/notifications')
            ->assertOk(); // Since notifications returns empty list or data
    }

    /**
     * @describe Wildcard Tokens
     */
    public function test_wildcard_tokens_bypass_all_checks(): void
    {
        // Create a wildcard token manually (representing super admin or legacy)
        $tokenName = 'legacy@device-legacy';
        $plainTextToken = $this->adminUser->createToken($tokenName, ['*']);

        $accessToken = $plainTextToken->accessToken;
        $accessToken->forceFill([
            'school_id' => $this->school->id,
            'device_id' => 'device-legacy',
            'app_type' => 'dashboard',
        ])->save();

        // Can access teacher route
        $this->withBearerToken($plainTextToken->plainTextToken)
            ->getJson('/api/v1/teacher/attendance/sessions/999/roster')
            ->assertStatus(404);

        // Can access dashboard route
        $this->withBearerToken($plainTextToken->plainTextToken)
            ->getJson('/api/v1/admin/search?q=test')
            ->assertOk();
    }

    /**
     * @describe Tenant Host Resolution (AUTH-003)
     */
    public function test_tenant_resolved_from_host_without_school_code_in_body(): void
    {
        // Register host in central database
        DB::connection('central')->table('school_domains')->insert([
            'school_id' => $this->school->id,
            'host' => 'alpha.edubridge.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call the app-specific endpoint on the host without school_code
        $response = $this->postJson('http://alpha.edubridge.test/api/v1/teacher/auth/login', [
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'device_id' => 'device-host-resolved',
        ]);

        $response->assertOk();
        $this->assertSame('teacher', $response->json('data.device_session.app_type'));
        $this->assertSame('alpha', $response->json('data.school.code'));
    }

    /**
     * @describe FCM Push Token Storage (AUTH-004)
     */
    public function test_fcm_push_token_storage_and_encryption(): void
    {
        $token = $this->getTeacherToken();

        $fcmToken = 'fcm-raw-token-123456789-abcdef';

        $response = $this->withBearerToken($token)
            ->putJson('/api/v1/auth/device/push-token', [
                'token' => $fcmToken,
                'platform' => 'android',
            ]);

        $response->assertNoContent();

        // Verify the record in the database
        $record = DB::connection('tenant')->table('device_tokens')->first();
        $this->assertNotNull($record);
        $this->assertSame($this->teacherUser->id, (int) $record->central_user_id);
        $this->assertSame('teacher', $record->app_type);
        $this->assertSame('android', $record->platform);
        $this->assertSame(hash('sha256', $fcmToken), $record->token_hash);
        $this->assertNotNull($record->last_seen_at);

        // Verify the token is stored encrypted in database
        $this->assertNotEquals($fcmToken, $record->token);

        // Eloquent Model decrypts it automatically
        $model = DeviceToken::first();
        $this->assertSame($fcmToken, $model->token);
    }

    /**
     * @describe School Onboarding Lookup (AUTH-005)
     */
    public function test_school_lookup_by_invitation_token(): void
    {
        // 1. Success case
        $token = $this->school->generateInvitationToken();

        $response = $this->postJson('/api/v1/school/lookup', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.school_name', 'Alpha School')
            ->assertJsonPath('data.school_code', 'alpha')
            ->assertJsonPath('data.api_base_url', 'http://alpha.edubridge.com'); // default fallback host

        // 2. Success case with domain registered
        DB::connection('central')->table('school_domains')->insert([
            'school_id' => $this->school->id,
            'host' => 'alpha-primary.edubridge.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/school/lookup', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.api_base_url', 'http://alpha-primary.edubridge.test');

        // 3. Invalid token case
        $this->postJson('/api/v1/school/lookup', [
            'token' => 'invalid-token-string',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TOKEN');

        // 4. Expired token case
        $expiredToken = Crypt::encryptString(json_encode([
            'school_id' => $this->school->id,
            'expires_at' => now()->subDay()->timestamp,
        ]));

        $this->postJson('/api/v1/school/lookup', [
            'token' => $expiredToken,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'EXPIRED_TOKEN');
    }

    private function getTeacherToken(): string
    {
        return $this->postJson('/api/v1/teacher/auth/login', [
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'device-teacher',
        ])->json('data.token');
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
        $this->teacherUser = User::query()->create([
            'name' => 'Teacher User',
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->parentUser = User::query()->create([
            'name' => 'Parent User',
            'email' => 'parent@example.test',
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

        // Add teacher role
        DB::connection('central')->table('school_user')->insert([
            'school_id' => $this->school->id,
            'user_id' => $this->teacherUser->id,
            'role_key' => 'teacher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add parent role
        DB::connection('central')->table('school_user')->insert([
            'school_id' => $this->school->id,
            'user_id' => $this->parentUser->id,
            'role_key' => 'parent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminUser = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        // Add admin role
        DB::connection('central')->table('school_user')->insert([
            'school_id' => $this->school->id,
            'user_id' => $this->adminUser->id,
            'role_key' => 'school_admin',
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

        // Assign roles in tenant database
        $teacherRoleId = DB::connection('tenant')->table('roles')->where('key', 'teacher')->value('id');
        DB::connection('tenant')->table('user_roles')->insert([
            'central_user_id' => $this->teacherUser->id,
            'role_id' => $teacherRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRoleId = DB::connection('tenant')->table('roles')->where('key', 'school_admin')->value('id');
        DB::connection('tenant')->table('user_roles')->insert([
            'central_user_id' => $this->adminUser->id,
            'role_id' => $adminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
