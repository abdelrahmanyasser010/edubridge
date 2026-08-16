<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentSessionManager;
use App\Actions\Payments\PaymentSettlementManager;
use App\Models\PaymentSession;
use App\Models\Student;
use App\Tenancy\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    private string $tenantDatabase;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('payment-settlement-tenant');
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

    public function test_refund_writes_reversal_queues_receipt_and_reconciles_day(): void
    {
        $payment = app(PaymentSessionManager::class);
        $settlement = app(PaymentSettlementManager::class);
        $fee = $payment->createFee(Student::query()->findOrFail($this->studentId), 'Fee', 100, 'SAR');
        $session = $payment->createSession($fee, 'moyasar', 'ps_refund');
        $event = ['id' => 'evt_paid_refund', 'provider_session_id' => 'ps_refund', 'status' => 'paid', 'amount' => 100, 'currency' => 'SAR'];
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'secret');
        $payment->handlePaidWebhook($event, $payload, $signature, $timestamp, 'secret');

        $settlement->requestReceipt(PaymentSession::query()->findOrFail($session->id));
        $settlement->refund(PaymentSession::query()->findOrFail($session->id), 40, 'refund_1');
        $summary = $settlement->dailyReconciliation(now()->toDateString());

        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'receipt.generate_requested'], 'tenant');
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'refund_reversal'], 'tenant');
        $this->assertSame('60.00', $summary['net_amount']);
    }

    private function seedStudent(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-SET-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
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
