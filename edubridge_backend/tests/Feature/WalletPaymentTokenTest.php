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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class WalletPaymentTokenTest extends TestCase
{
    private string $tenantDatabase;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('wallet-token-tenant');
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

    public function test_wallet_payment_token_is_hashed_single_use_and_limited(): void
    {
        $ledger = app(WalletLedger::class);
        $student = Student::query()->findOrFail($this->studentId);
        $ledger->creditTopUp($student, 'SAR', 100, 'pay_1', null);
        $issued = $ledger->issuePaymentToken($student, 'SAR', 30);

        $this->assertFalse(DB::connection('tenant')->table('wallet_payment_tokens')->where('token_hash', $issued['token'])->exists());

        $transaction = $ledger->deductByToken($issued['token'], 25, 'pos_1', null);
        $this->assertSame('-25.00', $transaction->amount);
        $this->assertDatabaseHas('wallets', ['student_id' => $this->studentId, 'cached_balance' => 75], 'tenant');

        $this->expectException(ConflictHttpException::class);
        $ledger->deductByToken($issued['token'], 1, 'pos_2', null);
    }

    private function seedStudent(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-WTOK-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
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
