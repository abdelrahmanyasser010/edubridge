<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\TenantNotFoundException;
use Illuminate\Support\Facades\DB;
use stdClass;

final class TenantConnectionResolver
{
    public function resolveByHost(string $host): Tenant
    {
        $normalizedHost = strtolower($host);

        $row = DB::connection('central')
            ->table('school_domains')
            ->join('schools', 'schools.id', '=', 'school_domains.school_id')
            ->join('tenant_connections', 'tenant_connections.school_id', '=', 'schools.id')
            ->where('school_domains.host', $normalizedHost)
            ->where('schools.status', 'active')
            ->where('tenant_connections.status', 'active')
            ->select([
                'schools.id as school_id',
                'tenant_connections.driver',
                'tenant_connections.database',
                'tenant_connections.host',
                'tenant_connections.port',
                'tenant_connections.username',
                'tenant_connections.secret_ref',
                'tenant_connections.options',
            ])
            ->first();

        if (! $row instanceof stdClass) {
            throw TenantNotFoundException::forHost($normalizedHost);
        }

        return $this->tenantFromRow($row);
    }

    public function resolveBySchoolId(int $schoolId): Tenant
    {
        $row = DB::connection('central')
            ->table('schools')
            ->join('tenant_connections', 'tenant_connections.school_id', '=', 'schools.id')
            ->where('schools.id', $schoolId)
            ->where('schools.status', 'active')
            ->where('tenant_connections.status', 'active')
            ->select([
                'schools.id as school_id',
                'tenant_connections.driver',
                'tenant_connections.database',
                'tenant_connections.host',
                'tenant_connections.port',
                'tenant_connections.username',
                'tenant_connections.secret_ref',
                'tenant_connections.options',
            ])
            ->first();

        if (! $row instanceof stdClass) {
            throw TenantNotFoundException::forSchoolId($schoolId);
        }

        return $this->tenantFromRow($row);
    }

    private function tenantFromRow(stdClass $row): Tenant
    {
        $options = is_string($row->options) ? json_decode($row->options, true) : [];

        return new Tenant(
            schoolId: (int) $row->school_id,
            driver: (string) $row->driver,
            database: (string) $row->database,
            host: is_string($row->host) ? $row->host : null,
            port: $row->port === null ? null : (int) $row->port,
            username: is_string($row->username) ? $row->username : null,
            secretRef: is_string($row->secret_ref) ? $row->secret_ref : null,
            options: is_array($options) ? $options : [],
        );
    }
}
