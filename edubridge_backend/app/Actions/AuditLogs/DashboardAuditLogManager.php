<?php

namespace App\Actions\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardAuditLogManager
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function logs(array $filters): LengthAwarePaginator
    {
        $paginator = AuditLog::query()
            ->when($filters['actor_id'] ?? null, fn ($query, mixed $actorId) => $query->where('actor_central_user_id', $actorId))
            ->when($filters['action'] ?? null, fn ($query, mixed $action) => $query->where('action', $action))
            ->when($filters['entity_type'] ?? null, fn ($query, mixed $entityType) => $query->where('subject_type', $entityType))
            ->when($filters['entity_id'] ?? null, fn ($query, mixed $entityId) => $query->where('subject_id', $entityId))
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->where('created_at', '>=', Carbon::parse((string) $from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->where('created_at', '<=', Carbon::parse((string) $to)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        /** @var Collection<int, AuditLog> $items */
        $items = collect($paginator->items());
        $actors = $this->actorMap($items);

        return $paginator->through(fn (AuditLog $log): array => $this->item($log, $actors));
    }

    /** @return array<string, mixed> */
    public function log(AuditLog $log): array
    {
        return $this->item($log, $this->actorMap(collect([$log])));
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<int, array{id: string, name: string, email: string}>
     */
    private function actorMap(Collection $logs): array
    {
        $actorIds = $logs
            ->pluck('actor_central_user_id')
            ->filter(fn (mixed $id): bool => $id !== null)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($actorIds === []) {
            return [];
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', $actorIds)->get(['id', 'name', 'email']);

        return $users
            ->mapWithKeys(fn (User $user): array => [
                (int) $user->id => [
                    'id' => (string) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{id: string, name: string, email: string}>  $actors
     * @return array<string, mixed>
     */
    private function item(AuditLog $log, array $actors): array
    {
        $actorId = $log->actor_central_user_id;

        return [
            'id' => (string) $log->id,
            'actor' => $actorId === null ? null : ($actors[(int) $actorId] ?? [
                'id' => (string) $actorId,
                'name' => null,
                'email' => null,
            ]),
            'action' => $log->action,
            'entity_type' => $log->subject_type,
            'entity_id' => $log->subject_id,
            'summary' => $this->summary($log),
            'ip_address' => $log->ip_address,
            'device_session_id' => null,
            'request_id' => $log->request_id,
            'before' => $this->safePayload($log->before),
            'after' => $this->safePayload($log->after),
            'created_at' => Carbon::parse((string) $log->created_at)->toISOString(),
        ];
    }

    private function summary(AuditLog $log): string
    {
        $subject = $log->subject_type === null ? 'record' : class_basename($log->subject_type);
        $subjectId = $log->subject_id === null ? '' : ' #'.$log->subject_id;

        return str_replace('.', ' ', $log->action).' on '.$subject.$subjectId;
    }

    /** @return array<string, mixed>|null */
    private function safePayload(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        return $this->redact($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);

            if (str_contains($normalized, 'password') || str_contains($normalized, 'token') || str_contains($normalized, 'secret') || str_contains($normalized, 'api_key') || str_contains($normalized, 'national_id') || str_contains($normalized, 'card')) {
                $payload[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }
}
