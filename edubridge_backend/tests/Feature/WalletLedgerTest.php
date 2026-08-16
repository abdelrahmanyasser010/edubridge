<?php

namespace Tests\Feature;

use App\Actions\Wallet\WalletLedger;
use App\Models\Student;
use App\Tenancy\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    private string $tenantDatabase;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('wallet-tenant');
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), ['database' => $this->tenantDatabase]));
        DB::purge('tenant');
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        app(TenantContext::class)->activate(new Tenant(1, 'sqlite', $this->tenantDatabase));
        $this->seedStudent();
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

    public function test_top_up_credit_creates_immutable_transaction_and_replays_by_reference(): void
    {
        $ledger = app(WalletLedger::class);
        $student = Student::query()->findOrFail($this->studentId);

        $first = $ledger->creditTopUp($student, 'SAR', 100.00, 'pay_1', null);
        $replay = $ledger->creditTopUp($student, 'SAR', 100.00, 'pay_1', null);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, DB::connection('tenant')->table('wallet_transactions')->count());
        $this->assertDatabaseHas('wallets', ['student_id' => $this->studentId, 'cached_balance' => 100, 'version' => 2], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.top_up_credited'], 'tenant');
    }

    private function seedStudent(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-WAL-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
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
