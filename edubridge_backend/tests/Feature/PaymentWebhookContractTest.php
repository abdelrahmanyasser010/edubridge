<?php

namespace Tests\Feature;

use App\Support\Payments\PaymentWebhookVerifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PaymentWebhookContractTest extends TestCase
{
    private string $tenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDatabase = $this->sqliteDatabasePath('payment-contract-tenant');
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), ['database' => $this->tenantDatabase]));
        DB::purge('tenant');
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('tenant');
        DB::purge('tenant');
        if (is_file($this->tenantDatabase)) {
            unlink($this->tenantDatabase);
        }
        parent::tearDown();
    }

    public function test_payment_webhook_signature_timestamp_and_replay_contract(): void
    {
        $verifier = new PaymentWebhookVerifier;
        $payload = '{"id":"evt_1","type":"payment.paid"}';
        $timestamp = (string) now()->timestamp;
        $secret = 'test-secret';
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $verifier->verify($payload, $signature, $timestamp, $secret);
        $verifier->reserveEvent('moyasar', 'evt_1', ['id' => 'evt_1']);
        $this->assertDatabaseHas('payment_webhook_events', ['provider' => 'moyasar', 'event_id' => 'evt_1'], 'tenant');

        $this->expectException(ConflictHttpException::class);
        $verifier->reserveEvent('moyasar', 'evt_1', ['id' => 'evt_1']);
    }

    public function test_payment_webhook_rejects_invalid_or_stale_signature(): void
    {
        $verifier = new PaymentWebhookVerifier;
        $payload = '{"id":"evt_bad"}';
        $secret = 'test-secret';

        try {
            $verifier->verify($payload, 'bad', (string) now()->timestamp, $secret);
            $this->fail('Invalid signature should fail.');
        } catch (AccessDeniedHttpException) {
            $this->assertTrue(true);
        }

        $oldTimestamp = (string) now()->subMinutes(10)->timestamp;
        $oldSignature = hash_hmac('sha256', $oldTimestamp.'.'.$payload, $secret);

        $this->expectException(AccessDeniedHttpException::class);
        $verifier->verify($payload, $oldSignature, $oldTimestamp, $secret);
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
