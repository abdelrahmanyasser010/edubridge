<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Outbox
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishAfterCommit(string $eventType, array $payload, ?\DateTimeInterface $availableAt = null): string
    {
        $this->tenantContext->tenant();
        $eventId = (string) Str::uuid();

        DB::connection('tenant')->afterCommit(function () use ($eventId, $eventType, $payload, $availableAt): void {
            $this->publishNow($eventId, $eventType, $payload, $availableAt);
        });

        return $eventId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishNow(string $eventId, string $eventType, array $payload, ?\DateTimeInterface $availableAt): void
    {
        DB::connection('tenant')->table('outbox_messages')->insert([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
