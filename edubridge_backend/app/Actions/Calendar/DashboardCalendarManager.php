<?php

namespace App\Actions\Calendar;

use App\Models\CalendarEvent;
use App\Support\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DashboardCalendarManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function events(array $filters): LengthAwarePaginator
    {
        return CalendarEvent::query()
            ->when($filters['type'] ?? null, fn ($query, mixed $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->where('starts_at', '>=', Carbon::parse((string) $from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->where('starts_at', '<=', Carbon::parse((string) $to)->endOfDay()))
            ->orderBy('starts_at')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->through(fn (CalendarEvent $event): array => $this->item($event));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorCentralUserId): array
    {
        $event = CalendarEvent::query()->create([
            ...$data,
            'audience_ids' => array_values($data['audience_ids'] ?? []),
            'all_day' => (bool) ($data['all_day'] ?? false),
            'status' => CalendarEvent::STATUS_ACTIVE,
            'created_by_central_user_id' => $actorCentralUserId,
        ]);

        $this->audit->record('calendar.event.created', CalendarEvent::class, (string) $event->id, null, [
            'title' => $event->title,
            'starts_at' => Carbon::parse($event->starts_at)->toISOString(),
        ]);

        return $this->item($event->refresh());
    }

    /** @param array<string, mixed> $data */
    public function update(CalendarEvent $event, array $data): array
    {
        $before = $this->item($event);
        if (array_key_exists('audience_ids', $data)) {
            $data['audience_ids'] = array_values($data['audience_ids'] ?? []);
        }

        $event->fill($data)->save();
        $this->audit->record('calendar.event.updated', CalendarEvent::class, (string) $event->id, $before, $this->item($event->refresh()));

        return $this->item($event);
    }

    public function cancel(CalendarEvent $event): array
    {
        $before = ['status' => $event->status];
        $event->forceFill(['status' => CalendarEvent::STATUS_CANCELLED])->save();
        $this->audit->record('calendar.event.cancelled', CalendarEvent::class, (string) $event->id, $before, ['status' => CalendarEvent::STATUS_CANCELLED]);

        return $this->item($event->refresh());
    }

    /** @return array<string, mixed> */
    public function item(CalendarEvent $event): array
    {
        return [
            'id' => (string) $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'starts_at' => Carbon::parse($event->starts_at)->toISOString(),
            'ends_at' => $event->ends_at === null ? null : Carbon::parse($event->ends_at)->toISOString(),
            'all_day' => $event->all_day,
            'audience_type' => $event->audience_type,
            'audience_ids' => $event->audience_ids ?? [],
            'location' => $event->location,
            'status' => $event->status,
            'created_by_central_user_id' => (string) $event->created_by_central_user_id,
        ];
    }
}
