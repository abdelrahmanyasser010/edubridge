<?php

namespace Tests\Feature;

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

class TransportAlertOptOutTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $supervisor;

    private User $parent;

    private int $routeId;

    private int $tripId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->centralDatabase = $this->sqliteDatabasePath('transport-alert-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('transport-alert-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);
        $this->seedIdentity();
        $this->assignRole($this->supervisor, 'transport_supervisor');
        $this->assignRole($this->parent, 'parent');
        $this->seedTransport();
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

    public function test_parent_opt_out_and_transport_alert_notify_deduped_parent(): void
    {
        $parentToken = $this->loginAndReturnToken($this->parent, 'alert-parent-device', 'parent');
        $this->withBearerToken($parentToken)
            ->postJson('/api/v1/parent/students/'.$this->studentId.'/transport/opt-outs', ['service_date' => '2026-08-03', 'direction' => 'pickup', 'reason' => 'Family pickup'])
            ->assertCreated();

        $supervisorToken = $this->loginAndReturnToken($this->supervisor, 'alert-supervisor-device', 'transport');
        $this->withBearerToken($supervisorToken)
            ->postJson('/api/v1/transport/routes/'.$this->routeId.'/alerts', ['bus_trip_id' => $this->tripId, 'type' => 'delay', 'message' => 'Bus delayed 10 minutes'])
            ->assertCreated();

        $this->assertDatabaseHas('bus_opt_outs', ['student_id' => $this->studentId, 'direction' => 'pickup'], 'tenant');
        $this->assertDatabaseHas('notifications', ['type' => 'transport.alert'], 'tenant');
        $this->assertSame(1, DB::connection('tenant')->table('notification_deliveries')->where('central_user_id', $this->parent->id)->where('channel', 'database')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'transport_alert.created'], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loginAndReturnToken(User $user, string $deviceId, string $appType): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();
        $token = $this->postJson("/api/v1/{$appType}/auth/login", [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Mobile',
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
        $this->supervisor = User::query()->create(['name' => 'Transport', 'email' => 'transport-alert@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->parent = User::query()->create(['name' => 'Parent', 'email' => 'transport-alert-parent@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        foreach ([[$this->supervisor, 'transport_supervisor'], [$this->parent, 'parent']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $user->id, 'role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedTransport(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId(['central_user_id' => $this->parent->id, 'full_name' => 'Parent', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-ALERT-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('student_parent')->insert(['student_id' => $this->studentId, 'parent_id' => $parentId, 'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true, 'valid_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->routeId = (int) DB::connection('tenant')->table('bus_routes')->insertGetId(['name' => 'North', 'code' => 'ALERT-N', 'capacity' => 20, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('bus_route_assignments')->insert(['bus_route_id' => $this->routeId, 'student_id' => $this->studentId, 'valid_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->tripId = (int) DB::connection('tenant')->table('bus_trips')->insertGetId(['bus_route_id' => $this->routeId, 'service_date' => '2026-08-03', 'direction' => 'pickup', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }
}
