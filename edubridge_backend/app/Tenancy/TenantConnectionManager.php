<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class TenantConnectionManager
{
    public function __construct(private readonly TenantContext $context) {}

    public function activate(Tenant $tenant): void
    {
        $this->disconnect();

        Config::set('database.connections.tenant', $this->connectionConfig($tenant));
        DB::purge('tenant');
        DB::reconnect('tenant');

        $this->context->activate($tenant);
    }

    public function disconnect(): void
    {
        DB::disconnect('tenant');
        DB::purge('tenant');
        $this->context->forget();
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $this->activate($tenant);

        try {
            return $callback();
        } finally {
            $this->disconnect();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(Tenant $tenant): array
    {
        $base = config('database.connections.tenant', []);

        if (! is_array($base) || ($base['driver'] ?? null) !== $tenant->driver) {
            $base = config('database.connections.'.$tenant->driver, []);
        }

        $config = is_array($base) ? $base : [];

        $config['driver'] = $tenant->driver;
        $config['database'] = $tenant->database;

        if ($tenant->host !== null) {
            $config['host'] = $tenant->host;
        }

        if ($tenant->port !== null) {
            $config['port'] = $tenant->port;
        }

        if ($tenant->username !== null) {
            $config['username'] = $tenant->username;
        }

        if ($tenant->options !== []) {
            $config['options'] = $tenant->options;
        }

        return $config;
    }
}
