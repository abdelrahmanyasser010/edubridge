<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentSessionManager;
use App\Models\Student;
use App\Tenancy\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PaymentSessionWebhookTest extends TestCase
{
    private string $tenantDatabase;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('payment-session-tenant');
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

    public function test_signed_webhook_marks_payment_paid_once_and_credits_wallet_once(): void
    {
        $manager = app(PaymentSessionManager::class);
        $fee = $manager->createFee(Student::query()->findOrFail($this->studentId), 'Meal top-up', 50, 'SAR');
        $session = $manager->createSession($fee, 'moyasar', 'ps_1');
        $event = ['id' => 'evt_paid_1', 'provider_session_id' => $session->provider_session_id, 'status' => 'paid', 'amount' => 50, 'currency' => 'SAR'];
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $secret = 'secret';
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $manager->handlePaidWebhook($event, $payload, $signature, $timestamp, $secret);
        $this->assertDatabaseHas('fees', ['id' => $fee->id, 'status' => 'paid'], 'tenant');
        $this->assertSame(1, DB::connection('tenant')->table('wallet_transactions')->count());

        $this->expectException(ConflictHttpException::class);
        $manager->handlePaidWebhook($event, $payload, $signature, $timestamp, $secret);
    }

    private function seedStudent(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-PAY-001', 'full_name' => 'Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
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
