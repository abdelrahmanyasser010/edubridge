<?php

namespace App\Actions\Transport;

use App\Actions\Notifications\NotificationManager;
use App\Models\BusRoute;
use App\Models\BusRouteAssignment;
use App\Models\StudentParent;
use App\Models\TransportAlert;
use App\Models\TransportContactDriverLog;
use App\Support\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardTransportManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationManager $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $routes = DB::connection('tenant')->table('bus_routes');
        $activeRoutes = (clone $routes)->where('status', BusRoute::STATUS_ACTIVE)->count();
        $activeTrips = DB::connection('tenant')->table('bus_trips')->whereDate('service_date', today()->toDateString())->where('status', 'active')->count();
        $delayedRoutes = DB::connection('tenant')
            ->table('transport_alerts')
            ->where('type', 'delay')
            ->whereDate('created_at', today()->toDateString())
            ->distinct('bus_route_id')
            ->count('bus_route_id');

        return [
            'routes' => (int) $activeRoutes,
            'on_route' => (int) $activeTrips,
            'delayed' => (int) $delayedRoutes,
            'assigned_students' => (int) DB::connection('tenant')->table('bus_route_assignments')->where('status', 'active')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function routes(array $filters): LengthAwarePaginator
    {
        return BusRoute::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->through(fn (BusRoute $route): array => $this->routeItem($route));
    }

    /** @return array<string, mixed> */
    public function route(BusRoute $route): array
    {
        return $this->routeItem($route);
    }

    /** @param array<string, mixed> $data */
    public function createRoute(array $data): array
    {
        $route = BusRoute::query()->create([
            ...$data,
            'status' => BusRoute::STATUS_ACTIVE,
        ]);

        $this->audit->record('dashboard.bus_route.created', BusRoute::class, (string) $route->id, null, [
            'code' => $route->code,
            'capacity' => $route->capacity,
        ]);

        return $this->routeItem($route->refresh());
    }

    /** @param array<string, mixed> $data */
    public function updateRoute(BusRoute $route, array $data): array
    {
        if ($route->status === BusRoute::STATUS_ARCHIVED) {
            throw new ConflictHttpException('Archived bus routes cannot be updated.');
        }

        if (isset($data['capacity']) && (int) $data['capacity'] < $this->activeAssignmentCount($route)) {
            throw new ConflictHttpException('Bus route capacity cannot be lower than active assignments.');
        }

        $before = $this->routeItem($route);
        $route->fill($data)->save();
        $this->audit->record('dashboard.bus_route.updated', BusRoute::class, (string) $route->id, $before, $this->routeItem($route->refresh()));

        return $this->routeItem($route);
    }

    public function archiveRoute(BusRoute $route): array
    {
        return DB::connection('tenant')->transaction(function () use ($route): array {
            $before = ['status' => $route->status];
            $route->forceFill(['status' => BusRoute::STATUS_ARCHIVED])->save();
            BusRouteAssignment::query()
                ->where('bus_route_id', $route->id)
                ->where('status', BusRouteAssignment::STATUS_ACTIVE)
                ->update(['status' => BusRouteAssignment::STATUS_ARCHIVED, 'updated_at' => now()]);

            $this->audit->record('dashboard.bus_route.archived', BusRoute::class, (string) $route->id, $before, [
                'status' => BusRoute::STATUS_ARCHIVED,
            ]);

            return $this->routeItem($route->refresh());
        });
    }

    /** @param array{student_id:int,valid_from:string,valid_until?:string|null} $data */
    public function assignStudent(BusRoute $route, array $data): BusRouteAssignment
    {
        if ($route->status !== BusRoute::STATUS_ACTIVE) {
            throw new ConflictHttpException('Bus route is not active.');
        }

        if ($this->activeAssignmentCount($route) >= $route->capacity) {
            throw new ConflictHttpException('Bus route capacity exceeded.');
        }

        if ($this->overlappingAssignmentExists((int) $data['student_id'], $data['valid_from'], $data['valid_until'] ?? null)) {
            throw new ConflictHttpException('Student already has an active bus route assignment.');
        }

        $assignment = BusRouteAssignment::query()->create([
            'bus_route_id' => $route->id,
            'student_id' => $data['student_id'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'status' => BusRouteAssignment::STATUS_ACTIVE,
        ]);

        $this->audit->record('dashboard.bus_route_assignment.created', BusRouteAssignment::class, (string) $assignment->id, null, [
            'bus_route_id' => (string) $route->id,
            'student_id' => (string) $assignment->student_id,
        ]);

        return $assignment->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateAssignment(BusRoute $route, int $assignmentId, array $data): BusRouteAssignment
    {
        $assignment = $this->assignmentForRoute($route, $assignmentId);
        $nextStatus = (string) ($data['status'] ?? $assignment->status);

        if ($nextStatus === BusRouteAssignment::STATUS_ACTIVE && $route->status !== BusRoute::STATUS_ACTIVE) {
            throw new ConflictHttpException('Assignments on archived bus routes cannot be activated.');
        }

        if ($nextStatus === BusRouteAssignment::STATUS_ACTIVE && $assignment->status !== BusRouteAssignment::STATUS_ACTIVE && $this->activeAssignmentCount($route) >= $route->capacity) {
            throw new ConflictHttpException('Bus route capacity exceeded.');
        }

        $validFrom = (string) ($data['valid_from'] ?? Carbon::parse($assignment->valid_from)->toDateString());
        $validUntil = array_key_exists('valid_until', $data)
            ? ($data['valid_until'] === null ? null : (string) $data['valid_until'])
            : ($assignment->valid_until === null ? null : Carbon::parse($assignment->valid_until)->toDateString());

        if ($nextStatus === BusRouteAssignment::STATUS_ACTIVE && $this->overlappingAssignmentExists((int) $assignment->student_id, $validFrom, $validUntil, (int) $assignment->id)) {
            throw new ConflictHttpException('Student already has an active bus route assignment.');
        }

        $before = $assignment->only(['valid_from', 'valid_until', 'status']);
        $assignment->fill([
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'status' => $nextStatus,
        ])->save();

        $this->audit->record('dashboard.bus_route_assignment.updated', BusRouteAssignment::class, (string) $assignment->id, $before, $assignment->only(['valid_from', 'valid_until', 'status']));

        return $assignment->refresh();
    }

    public function archiveAssignment(BusRoute $route, int $assignmentId): BusRouteAssignment
    {
        $assignment = $this->assignmentForRoute($route, $assignmentId);
        $before = ['status' => $assignment->status];
        $assignment->forceFill(['status' => BusRouteAssignment::STATUS_ARCHIVED])->save();
        $this->audit->record('dashboard.bus_route_assignment.archived', BusRouteAssignment::class, (string) $assignment->id, $before, [
            'status' => BusRouteAssignment::STATUS_ARCHIVED,
        ]);

        return $assignment->refresh();
    }

    /** @return list<array<string, mixed>> */
    public function passengers(BusRoute $route): array
    {
        return DB::connection('tenant')
            ->table('bus_route_assignments')
            ->join('students', 'students.id', '=', 'bus_route_assignments.student_id')
            ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
            ->leftJoin('student_parent', function ($join): void {
                $join->on('student_parent.student_id', '=', 'students.id')
                    ->where('student_parent.status', '=', StudentParent::STATUS_ACTIVE)
                    ->where('student_parent.is_primary', '=', true);
            })
            ->leftJoin('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('bus_route_assignments.bus_route_id', $route->id)
            ->where('bus_route_assignments.status', 'active')
            ->orderBy('students.full_name')
            ->get([
                'bus_route_assignments.id as assignment_id',
                'students.id as student_id',
                'students.full_name as student_name',
                'students.admission_number',
                'sections.name as section_name',
                'parents.full_name as parent_name',
                'parents.phone as parent_phone',
                'bus_route_assignments.valid_from',
                'bus_route_assignments.valid_until',
            ])
            ->map(fn (object $row): array => [
                'assignment_id' => (string) $row->assignment_id,
                'student_id' => (string) $row->student_id,
                'student_name' => (string) $row->student_name,
                'admission_number' => (string) $row->admission_number,
                'section_name' => $row->section_name === null ? null : (string) $row->section_name,
                'parent_name' => $row->parent_name === null ? null : (string) $row->parent_name,
                'parent_phone' => $row->parent_phone === null ? null : (string) $row->parent_phone,
                'valid_from' => (string) $row->valid_from,
                'valid_until' => $row->valid_until === null ? null : (string) $row->valid_until,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function events(BusRoute $route): array
    {
        $tracking = DB::connection('tenant')
            ->table('bus_tracking_events')
            ->join('bus_trips', 'bus_trips.id', '=', 'bus_tracking_events.bus_trip_id')
            ->where('bus_trips.bus_route_id', $route->id)
            ->orderByDesc('bus_tracking_events.recorded_at')
            ->limit(50)
            ->get(['bus_tracking_events.id', 'bus_tracking_events.latitude', 'bus_tracking_events.longitude', 'bus_tracking_events.recorded_at'])
            ->map(fn (object $row): array => [
                'type' => 'tracking',
                'id' => (string) $row->id,
                'summary' => 'GPS position recorded',
                'occurred_at' => Carbon::parse((string) $row->recorded_at)->toJSON(),
                'data' => [
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                ],
            ]);

        $alerts = DB::connection('tenant')
            ->table('transport_alerts')
            ->where('bus_route_id', $route->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'type', 'message', 'delay_minutes', 'channels', 'created_at'])
            ->map(fn (object $row): array => [
                'type' => 'alert',
                'id' => (string) $row->id,
                'summary' => (string) $row->message,
                'occurred_at' => Carbon::parse((string) $row->created_at)->toJSON(),
                'data' => [
                    'alert_type' => (string) $row->type,
                    'delay_minutes' => $row->delay_minutes === null ? null : (int) $row->delay_minutes,
                    'channels' => $row->channels === null ? [] : json_decode((string) $row->channels, true, 512, JSON_THROW_ON_ERROR),
                ],
            ]);

        $contacts = DB::connection('tenant')
            ->table('transport_contact_driver_logs')
            ->where('bus_route_id', $route->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'outcome', 'notes', 'created_at'])
            ->map(fn (object $row): array => [
                'type' => 'contact_driver',
                'id' => (string) $row->id,
                'summary' => (string) $row->outcome,
                'occurred_at' => Carbon::parse((string) $row->created_at)->toJSON(),
                'data' => ['notes' => $row->notes === null ? null : (string) $row->notes],
            ]);

        return $tracking
            ->concat($alerts)
            ->concat($contacts)
            ->sortByDesc('occurred_at')
            ->take(100)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function delayAlert(BusRoute $route, array $data, int $actorCentralUserId): TransportAlert
    {
        $channels = array_values(array_unique($data['channels'] ?? ['database', 'push']));
        $alert = TransportAlert::query()->create([
            'bus_route_id' => $route->id,
            'bus_trip_id' => $data['bus_trip_id'] ?? null,
            'type' => 'delay',
            'message' => $data['message'],
            'delay_minutes' => $data['delay_minutes'],
            'channels' => $channels,
            'created_by_central_user_id' => $actorCentralUserId,
        ]);

        $recipientIds = $this->routeParentCentralUserIds($route);
        if ($recipientIds !== []) {
            $this->notifications->create('transport.delay', 'Transport delay', $alert->message, $recipientIds, [
                'route_id' => (string) $route->id,
                'alert_id' => (string) $alert->id,
                'delay_minutes' => (string) $alert->delay_minutes,
                'channels' => $channels,
            ], $actorCentralUserId);
        }

        $this->audit->record('transport.delay_alert.created', TransportAlert::class, (string) $alert->id, null, [
            'bus_route_id' => (string) $route->id,
            'recipient_count' => count($recipientIds),
            'delay_minutes' => (string) $alert->delay_minutes,
        ]);

        return $alert->refresh();
    }

    /** @param array<string, mixed> $data */
    public function contactDriverLog(BusRoute $route, array $data, int $actorCentralUserId): TransportContactDriverLog
    {
        $log = TransportContactDriverLog::query()->create([
            'bus_route_id' => $route->id,
            'driver_phone' => $route->driver_phone,
            'outcome' => $data['outcome'],
            'notes' => $data['notes'] ?? null,
            'created_by_central_user_id' => $actorCentralUserId,
        ]);

        $this->audit->record('transport.driver_contact.logged', TransportContactDriverLog::class, (string) $log->id, null, [
            'bus_route_id' => (string) $route->id,
            'outcome' => $log->outcome,
        ]);

        return $log->refresh();
    }

    /** @return array<string, mixed> */
    private function routeItem(BusRoute $route): array
    {
        $latestTrip = DB::connection('tenant')
            ->table('bus_trips')
            ->where('bus_route_id', $route->id)
            ->whereDate('service_date', today()->toDateString())
            ->orderByDesc('id')
            ->first(['id', 'status']);

        $latestLocation = DB::connection('tenant')
            ->table('bus_tracking_events')
            ->join('bus_trips', 'bus_trips.id', '=', 'bus_tracking_events.bus_trip_id')
            ->where('bus_trips.bus_route_id', $route->id)
            ->orderByDesc('bus_tracking_events.recorded_at')
            ->first(['bus_tracking_events.latitude', 'bus_tracking_events.longitude', 'bus_tracking_events.recorded_at']);

        $hasDelayToday = DB::connection('tenant')
            ->table('transport_alerts')
            ->where('bus_route_id', $route->id)
            ->where('type', 'delay')
            ->whereDate('created_at', today()->toDateString())
            ->exists();

        return [
            'id' => (string) $route->id,
            'route_name' => $route->name,
            'code' => $route->code,
            'plate_number' => $route->plate_number,
            'driver_name' => $route->driver_name,
            'driver_phone' => $route->driver_phone,
            'supervisor_name' => $route->supervisor_name,
            'capacity' => (int) $route->capacity,
            'status' => $hasDelayToday ? 'delayed' : ($latestTrip?->status === 'active' ? 'on_route' : $route->status),
            'assigned_students_count' => (int) DB::connection('tenant')->table('bus_route_assignments')->where('bus_route_id', $route->id)->where('status', 'active')->count(),
            'estimated_arrival' => $this->timeString($route->estimated_arrival_time),
            'last_location' => $latestLocation === null ? null : [
                'lat' => (float) $latestLocation->latitude,
                'lng' => (float) $latestLocation->longitude,
                'recorded_at' => Carbon::parse((string) $latestLocation->recorded_at)->toJSON(),
            ],
        ];
    }

    /** @return list<int> */
    private function routeParentCentralUserIds(BusRoute $route): array
    {
        return DB::connection('tenant')
            ->table('bus_route_assignments')
            ->join('student_parent', 'student_parent.student_id', '=', 'bus_route_assignments.student_id')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('bus_route_assignments.bus_route_id', $route->id)
            ->where('bus_route_assignments.status', 'active')
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->whereNotNull('parents.central_user_id')
            ->pluck('parents.central_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function timeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 5);
    }

    private function activeAssignmentCount(BusRoute $route): int
    {
        return BusRouteAssignment::query()
            ->where('bus_route_id', $route->id)
            ->where('status', BusRouteAssignment::STATUS_ACTIVE)
            ->count();
    }

    private function assignmentForRoute(BusRoute $route, int $assignmentId): BusRouteAssignment
    {
        $assignment = BusRouteAssignment::query()
            ->where('bus_route_id', $route->id)
            ->where('id', $assignmentId)
            ->first();

        if (! $assignment instanceof BusRouteAssignment) {
            throw new NotFoundHttpException;
        }

        return $assignment;
    }

    private function overlappingAssignmentExists(int $studentId, string $validFrom, ?string $validUntil, ?int $ignoreAssignmentId = null): bool
    {
        return BusRouteAssignment::query()
            ->where('student_id', $studentId)
            ->where('status', BusRouteAssignment::STATUS_ACTIVE)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->where('id', '!=', $ignoreAssignmentId))
            ->where('valid_from', '<=', $validUntil ?? '9999-12-31')
            ->where(function ($query) use ($validFrom): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $validFrom);
            })
            ->exists();
    }
}
