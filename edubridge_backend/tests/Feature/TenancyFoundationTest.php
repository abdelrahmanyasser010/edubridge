<?php

namespace Tests\Feature;

use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TenancyFoundationTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantAlphaDatabase;

    private string $tenantBetaDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('central');
        $this->tenantAlphaDatabase = $this->sqliteDatabasePath('tenant-alpha');
        $this->tenantBetaDatabase = $this->sqliteDatabasePath('tenant-beta');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantAlphaDatabase);

        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);

        $this->migrateTenantDatabase($this->tenantAlphaDatabase);
        $this->migrateTenantDatabase($this->tenantBetaDatabase);
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        DB::disconnect('central');
        DB::purge('central');
        gc_collect_cycles();

        foreach ([$this->centralDatabase, $this->tenantAlphaDatabase, $this->tenantBetaDatabase] as $database) {
            if (is_file($database)) {
                unlink($database);
            }
        }

        parent::tearDown();
    }

    public function test_central_and_tenant_migrations_are_separate(): void
    {
        $this->assertTrue(Schema::connection('central')->hasTable('schools'));
        $this->assertTrue(Schema::connection('central')->hasTable('school_domains'));
        $this->assertTrue(Schema::connection('central')->hasTable('tenant_connections'));
        $this->assertFalse(Schema::connection('central')->hasTable('roles'));

        $this->configureSqliteConnection('tenant', $this->tenantAlphaDatabase);

        $this->assertTrue(Schema::connection('tenant')->hasTable('roles'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('permissions'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('audit_logs'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('idempotency_keys'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('outbox_messages'));
        $this->assertFalse(Schema::connection('tenant')->hasTable('schools'));
    }

    public function test_resolver_switches_tenant_connection_without_cross_tenant_reads(): void
    {
        [$alphaSchoolId, $betaSchoolId] = $this->seedCentralSchools();

        $resolver = app(TenantConnectionResolver::class);
        $manager = app(TenantConnectionManager::class);
        $context = app(TenantContext::class);

        $alphaTenant = $resolver->resolveByHost('alpha.test');
        $betaTenant = $resolver->resolveByHost('beta.test');

        $this->assertSame($alphaSchoolId, $alphaTenant->schoolId);
        $this->assertSame($betaSchoolId, $betaTenant->schoolId);

        $manager->run($alphaTenant, function () use ($context) {
            $this->assertTrue(DB::connection('tenant')->table('roles')->insert([
                'key' => 'alpha-admin',
                'name' => json_encode(['en' => 'Alpha Admin']),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $this->assertTrue($context->active());
        });

        $this->assertFalse($context->active());

        $manager->run($betaTenant, function () {
            DB::connection('tenant')->table('roles')->insert([
                'key' => 'beta-admin',
                'name' => json_encode(['en' => 'Beta Admin']),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $manager->run($alphaTenant, function () {
            $this->assertSame(['alpha-admin'], DB::connection('tenant')->table('roles')->pluck('key')->all());
        });

        $manager->run($betaTenant, function () {
            $this->assertSame(['beta-admin'], DB::connection('tenant')->table('roles')->pluck('key')->all());
        });
    }

    public function test_tenant_context_is_cleared_after_exception(): void
    {
        [$alphaSchoolId] = $this->seedCentralSchools();

        $tenant = app(TenantConnectionResolver::class)->resolveBySchoolId($alphaSchoolId);
        $manager = app(TenantConnectionManager::class);
        $context = app(TenantContext::class);

        try {
            $manager->run($tenant, function () {
                throw new RuntimeException('tenant failure');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('tenant failure', $exception->getMessage());
        }

        $this->assertFalse($context->active());
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

    private function migrateTenantDatabase(string $database): void
    {
        $this->configureSqliteConnection('tenant', $database);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    /**
     * @return array{0: int, 1?: int}
     */
    private function seedCentralSchools(): array
    {
        $alphaSchoolId = $this->createCentralSchool('alpha', 'alpha.test', $this->tenantAlphaDatabase);
        $betaSchoolId = $this->createCentralSchool('beta', 'beta.test', $this->tenantBetaDatabase);

        return [$alphaSchoolId, $betaSchoolId];
    }

    private function createCentralSchool(string $code, string $host, string $database): int
    {
        $schoolId = DB::connection('central')->table('schools')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => ucfirst($code).' School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('school_domains')->insert([
            'school_id' => $schoolId,
            'host' => $host,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $schoolId,
            'driver' => 'sqlite',
            'database' => $database,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $schoolId;
    }
}
