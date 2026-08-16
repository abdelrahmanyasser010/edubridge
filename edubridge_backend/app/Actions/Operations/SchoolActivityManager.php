<?php

namespace App\Actions\Operations;

use App\Models\SchoolActivity;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SchoolActivityManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return LengthAwarePaginator<int, SchoolActivity> */
    public function list(array $filters): LengthAwarePaginator
    {
        return SchoolActivity::query()
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from) => $query->where('starts_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to) => $query->where('starts_at', '<=', $to))
            ->withCount(['registrations as active_registrations_count' => fn ($query) => $query->where('status', '!=', 'cancelled')])
            ->orderByDesc('starts_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function create(array $data, int $actorId): SchoolActivity
    {
        $activity = SchoolActivity::query()->create($data)->refresh();
        $this->audit->record('school_activity.created', SchoolActivity::class, (string) $activity->id, null, $activity->only(['title', 'starts_at', 'status', 'fee_amount_minor', 'currency']));

        return $activity;
    }

    public function update(SchoolActivity $activity, array $data, int $actorId): SchoolActivity
    {
        if ($activity->status === SchoolActivity::STATUS_COMPLETED && array_key_exists('starts_at', $data)) {
            throw new ConflictHttpException('Completed activities cannot change their schedule.');
        }

        $before = $activity->only(['title', 'starts_at', 'ends_at', 'capacity', 'fee_amount_minor', 'status']);
        $activity->fill($data)->save();
        $this->audit->record('school_activity.updated', SchoolActivity::class, (string) $activity->id, $before, $activity->only(['title', 'starts_at', 'ends_at', 'capacity', 'fee_amount_minor', 'status']));

        return $activity->refresh();
    }

    public function cancel(SchoolActivity $activity, int $actorId): SchoolActivity
    {
        if ($activity->status === SchoolActivity::STATUS_COMPLETED) {
            throw new ConflictHttpException('Completed activities cannot be cancelled.');
        }

        return $this->update($activity, ['status' => SchoolActivity::STATUS_CANCELLED], $actorId);
    }
}
