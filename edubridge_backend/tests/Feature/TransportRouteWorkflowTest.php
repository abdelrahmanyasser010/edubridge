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

class TransportRouteWorkflowTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $supervisor;

    /** @var list<int> */
    private array $studentIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->centralDatabase = $this->sqliteDatabasePath('transport-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('transport-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);
        $this->seedIdentity();
        $this->assignRole($this->supervisor, 'transport_supervisor');
        $this->seedStudents();
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

    public function test_transport_supervisor_creates_route_assignments_and_trip_with_capacity_guard(): void
    {
        $token = $this->loginAndReturnToken($this->supervisor);
        $routeId = $this->withBearerToken($token)
            ->postJson('/api/v1/transport/routes', ['name' => 'North Route', 'code' => 'NORTH', 'capacity' => 1, 'driver_name' => 'Driver'])
            ->assertCreated()
            ->assertJsonPath('data.capacity', 1)
            ->json('data.id');

        $this->assertIsString($routeId);

        $this->withBearerToken($token)
            ->postJson('/api/v1/transport/routes/'.$routeId.'/assignments', ['student_id' => $this->studentIds[0], 'valid_from' => '2026-08-03'])
            ->assertCreated();

        $this->withBearerToken($token)
            ->postJson('/api/v1/transport/routes/'.$routeId.'/assignments', ['student_id' => $this->studentIds[1], 'valid_from' => '2026-08-03'])
            ->assertConflict();

        $this->withBearerToken($token)
            ->postJson('/api/v1/transport/routes/'.$routeId.'/trips', ['service_date' => '2026-08-03', 'direction' => 'pickup'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('audit_logs', ['action' => 'bus_trip.created'], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loginAndReturnToken(User $user): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();
        $token = $this->postJson('/api/v1/transport/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'transport-device',
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
        $this->supervisor = User::query()->create(['name' => 'Transport', 'email' => 'transport@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $this->supervisor->id, 'role_key' => 'transport_supervisor', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedStudents(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['S-TRN-001', 'S-TRN-002'] as $admission) {
            $this->studentIds[] = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => $admission, 'full_name' => $admission, 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
