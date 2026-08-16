<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IdempotencyService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): IdempotencyResult  $callback
     */
    public function run(string $clientKey, string $operation, array $payload, Closure $callback, ?int $actorCentralUserId = null, int $ttlMinutes = 1440): IdempotencyResult
    {
        $this->tenantContext->tenant();

        $requestHash = $this->hashPayload($payload);
        $connection = DB::connection('tenant');
        $existing = $connection->table('idempotency_keys')
            ->where('client_key', $clientKey)
            ->where('operation', $operation)
            ->first();

        if ($existing !== null) {
            if ($existing->request_hash !== $requestHash) {
                throw new ConflictHttpException('Idempotency key was already used with a different payload.');
            }

            if ($existing->response_status === null || $existing->response_payload === null) {
                throw new ConflictHttpException('Idempotency request is already in progress.');
            }

            return new IdempotencyResult(
                payload: json_decode((string) $existing->response_payload, true, flags: JSON_THROW_ON_ERROR),
                status: (int) $existing->response_status,
                replayed: true,
            );
        }

        $now = now();
        $connection->table('idempotency_keys')->insert([
            'actor_central_user_id' => $actorCentralUserId,
            'client_key' => $clientKey,
            'operation' => $operation,
            'request_hash' => $requestHash,
            'expires_at' => $now->copy()->addMinutes($ttlMinutes),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $result = $callback();

            $connection->table('idempotency_keys')
                ->where('client_key', $clientKey)
                ->where('operation', $operation)
                ->update([
                    'response_payload' => json_encode($result->payload, JSON_THROW_ON_ERROR),
                    'response_status' => $result->status,
                    'updated_at' => now(),
                ]);

            return $result;
        } catch (\Throwable $exception) {
            $connection->table('idempotency_keys')
                ->where('client_key', $clientKey)
                ->where('operation', $operation)
                ->delete();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
