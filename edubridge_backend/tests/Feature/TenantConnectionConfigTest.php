<?php

namespace Tests\Feature;

use App\Tenancy\Tenant;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Tests\TestCase;

class TenantConnectionConfigTest extends TestCase
{
    public function test_mysql_tenant_connection_keeps_password_from_named_tenant_connection(): void
    {
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => 'generic-host',
            'database' => 'generic',
            'username' => 'generic-user',
            'password' => 'generic-secret',
        ]);

        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => 'tenant-default-host',
            'database' => 'tenant-placeholder',
            'username' => 'tenant-default-user',
            'password' => 'tenant-secret',
            'charset' => 'utf8mb4',
        ]);

        $manager = new TenantConnectionManager(new TenantContext);
        $method = new ReflectionMethod($manager, 'connectionConfig');

        $config = $method->invoke($manager, new Tenant(
            schoolId: 1,
            driver: 'mysql',
            database: 'school_alpha',
            host: 'tenant-host',
            port: 3307,
            username: 'tenant-user',
        ));

        $this->assertIsArray($config);
        $this->assertSame('mysql', $config['driver']);
        $this->assertSame('school_alpha', $config['database']);
        $this->assertSame('tenant-host', $config['host']);
        $this->assertSame(3307, $config['port']);
        $this->assertSame('tenant-user', $config['username']);
        $this->assertSame('tenant-secret', $config['password']);
        $this->assertSame('utf8mb4', $config['charset']);
    }
}
