<?php

namespace Tests\Feature;

use App\Actions\Reporting\OperationalReportManager;
use App\Tenancy\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReportManagerTest extends TestCase
{
    private string $tenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('report-tenant');
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), ['database' => $this->tenantDatabase]));
        DB::purge('tenant');
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        app(TenantContext::class)->activate(new Tenant(1, 'sqlite', $this->tenantDatabase));
    }

    protected function tearDown(): void
    {
        DB::disconnect('tenant');
        DB::purge('tenant');
        app(TenantContext::class)->forget();
        if (is_file($this->tenantDatabase)) {
            unlink($this->tenantDatabase);
        }
        parent::tearDown();
    }

    public function test_operational_report_is_scoped_and_export_is_async(): void
    {
        $manager = app(OperationalReportManager::class);
        $overview = $manager->dailyOverview(now()->toDateString());
        $manager->requestExport('daily_overview', now()->toDateString(), 123);

        $this->assertArrayHasKey('open_fees', $overview);
        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'report.export_requested'], 'tenant');
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
}
