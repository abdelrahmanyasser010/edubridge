<?php

namespace Tests\Feature;

use App\Actions\Operations\BroadcastSupportManager;
use App\Tenancy\Tenant;
use App\Tenancy\TenantContext;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BroadcastSupportManagerTest extends TestCase
{
    private string $tenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('ops-broadcast-tenant');
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), ['database' => $this->tenantDatabase]));
        DB::purge('tenant');
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);
        app(TenantContext::class)->activate(new Tenant(1, 'sqlite', $this->tenantDatabase));
        $roleId = DB::connection('tenant')->table('roles')->where('key', 'parent')->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => 10, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
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

    public function test_broadcast_targets_recipients_and_support_ticket_records_replies(): void
    {
        $manager = app(BroadcastSupportManager::class);
        $manager->broadcast('parent', 'Notice', 'Hello', null, 1);
        $ticketId = $manager->openTicket(10, 'Help', 'Need support');
        $manager->reply($ticketId, 1, 'We are checking');

        $this->assertDatabaseHas('notifications', ['type' => 'broadcast.message'], 'tenant');
        $this->assertDatabaseHas('notification_deliveries', ['central_user_id' => 10], 'tenant');
        $this->assertSame(2, DB::connection('tenant')->table('support_ticket_replies')->where('support_ticket_id', $ticketId)->count());
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
