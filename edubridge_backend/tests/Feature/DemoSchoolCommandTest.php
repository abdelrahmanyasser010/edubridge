<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoSchoolCommandTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('demo-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('demo-tenant');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);
    }

    protected function tearDown(): void
    {
        DB::disconnect('central');
        DB::disconnect('tenant');
        DB::purge('central');
        DB::purge('tenant');

        foreach ([$this->centralDatabase, $this->tenantDatabase] as $database) {
            if (is_file($database)) {
                unlink($database);
            }
        }

        parent::tearDown();
    }

    public function test_demo_school_command_migrates_and_seeds_idempotent_demo_data(): void
    {
        $this->artisan('edubridge:demo-school', [
            '--migrate' => true,
            '--school-code' => 'alpha',
            '--tenant-database' => $this->tenantDatabase,
        ])->assertExitCode(0);

        $this->artisan('edubridge:demo-school', [
            '--school-code' => 'alpha',
            '--tenant-database' => $this->tenantDatabase,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('schools', ['code' => 'alpha', 'status' => 'active'], 'central');
        $this->assertDatabaseHas('users', ['email' => 'demo-admin@example.test', 'status' => 'active'], 'central');
        $this->assertDatabaseHas('school_user', ['role_key' => 'school_admin', 'status' => 'active'], 'central');
        $this->assertDatabaseHas('tenant_connections', ['database' => $this->tenantDatabase, 'status' => 'active'], 'central');

        $this->assertDatabaseHas('students', ['admission_number' => 'S-DEMO-001', 'status' => 'active'], 'tenant');
        $this->assertDatabaseHas('teachers', ['employee_number' => 'T-DEMO-001', 'status' => 'active'], 'tenant');
        $this->assertDatabaseHas('parents', ['email' => 'demo-parent@example.test', 'status' => 'active'], 'tenant');
        $this->assertDatabaseHas('bus_routes', ['code' => 'DEMO-BUS', 'status' => 'active'], 'tenant');
        $this->assertDatabaseHas('fees', ['title' => 'Demo Meal Top-up', 'currency' => 'SAR', 'status' => 'open'], 'tenant');

        $this->assertSame(1, DB::connection('central')->table('schools')->where('code', 'alpha')->count());
        $this->assertSame(1, DB::connection('tenant')->table('students')->where('admission_number', 'S-DEMO-001')->count());
        $this->assertGreaterThanOrEqual(5, DB::connection('tenant')->table('user_roles')->count());
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
}
