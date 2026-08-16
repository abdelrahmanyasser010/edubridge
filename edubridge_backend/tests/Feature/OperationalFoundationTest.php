<?php

namespace Tests\Feature;

use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\IdempotencyResult;
use App\Support\IdempotencyService;
use App\Support\Outbox;
use App\Tenancy\Tenant;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OperationalFoundationTest extends TestCase
{
    private string $tenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabase = $this->sqliteDatabasePath('ops-tenant');
        Config::set('database.connections.tenant', array_merge(config('database.connections.sqlite'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        app(TenantConnectionManager::class)->activate(new Tenant(
            schoolId: 1,
            driver: 'sqlite',
            database: $this->tenantDatabase,
        ));
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        gc_collect_cycles();

        if (is_file($this->tenantDatabase)) {
            unlink($this->tenantDatabase);
        }

        parent::tearDown();
    }

    public function test_audit_logger_records_request_id_and_redacts_sensitive_data(): void
    {
        $request = Request::create('/api/v1/_test/audit', 'POST', server: ['REMOTE_ADDR' => '127.0.0.9']);
        $request->attributes->set(ApiResponse::REQUEST_ID_ATTRIBUTE, 'request-audit-001');

        app(AuditLogger::class)->record(
            action: 'attendance.submit',
            subjectType: 'attendance_record',
            subjectId: '42',
            before: ['status' => 'draft', 'token' => 'secret-token'],
            after: ['status' => 'submitted', 'nested' => ['password' => 'hidden']],
            request: $request,
        );

        $row = DB::connection('tenant')->table('audit_logs')->first();

        $this->assertSame('attendance.submit', $row->action);
        $this->assertSame('request-audit-001', $row->request_id);
        $this->assertSame('127.0.0.9', $row->ip_address);
        $this->assertSame('[redacted]', json_decode($row->before, true, flags: JSON_THROW_ON_ERROR)['token']);
        $this->assertSame('[redacted]', json_decode($row->after, true, flags: JSON_THROW_ON_ERROR)['nested']['password']);
    }

    public function test_idempotency_replays_same_payload_and_rejects_payload_drift(): void
    {
        $calls = 0;
        $service = app(IdempotencyService::class);

        $first = $service->run('idem-1', 'attendance.submit', ['records' => [1, 2]], function () use (&$calls) {
            $calls++;

            return new IdempotencyResult(['submitted' => true], 201, false);
        });

        $second = $service->run('idem-1', 'attendance.submit', ['records' => [1, 2]], function () use (&$calls) {
            $calls++;

            return new IdempotencyResult(['submitted' => false], 200, false);
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame(['submitted' => true], $second->payload);
        $this->assertSame(201, $second->status);

        $this->expectException(ConflictHttpException::class);

        $service->run('idem-1', 'attendance.submit', ['records' => [3]], function () {
            return new IdempotencyResult(['submitted' => true], 201, false);
        });
    }

    public function test_idempotency_clears_reservation_when_operation_fails(): void
    {
        $service = app(IdempotencyService::class);

        try {
            $service->run('idem-fails', 'wallet.deduct', ['amount' => '10.00'], function () {
                throw new \RuntimeException('deduct failed');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('deduct failed', $exception->getMessage());
        }

        $this->assertFalse(DB::connection('tenant')->table('idempotency_keys')->where('client_key', 'idem-fails')->exists());
    }

    public function test_outbox_publishes_only_after_successful_commit(): void
    {
        $connection = DB::connection('tenant');

        $connection->beginTransaction();
        app(Outbox::class)->publishAfterCommit('attendance.submitted', ['record_id' => 1]);
        $this->assertSame(0, $connection->table('outbox_messages')->count());
        $connection->rollBack();
        $this->assertSame(0, $connection->table('outbox_messages')->count());

        $connection->beginTransaction();
        $eventId = app(Outbox::class)->publishAfterCommit('attendance.submitted', ['record_id' => 2]);
        $this->assertSame(0, $connection->table('outbox_messages')->count());
        $connection->commit();

        $row = $connection->table('outbox_messages')->first();
        $this->assertSame($eventId, $row->event_id);
        $this->assertSame('attendance.submitted', $row->event_type);
        $this->assertSame('pending', $row->status);
        $this->assertSame(['record_id' => 2], json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR));
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
