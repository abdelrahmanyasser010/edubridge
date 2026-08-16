<?php

namespace App\Actions\Transport;

use App\Actions\Notifications\NotificationManager;
use App\Models\BusOptOut;
use App\Models\BusRoute;
use App\Models\BusRouteAssignment;
use App\Models\BusTrackingEvent;
use App\Models\BusTrip;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\TransportAlert;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransportManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationManager $notifications,
    ) {}

    /** @param array{name:string,code:string,capacity:int,driver_name?:string|null} $data */
    public function createRoute(array $data): BusRoute
    {
        $route = BusRoute::query()->create([
            ...$data,
            'status' => BusRoute::STATUS_ACTIVE,
        ]);

        $this->audit->record('bus_route.created', BusRoute::class, (string) $route->id, null, ['capacity' => $route->capacity]);

        return $route->refresh();
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

        $hasActiveAssignment = BusRouteAssignment::query()
            ->where('student_id', $data['student_id'])
            ->where('status', BusRouteAssignment::STATUS_ACTIVE)
            ->where(function ($query) use ($data) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $data['valid_from']);
            })
            ->exists();

        if ($hasActiveAssignment) {
            throw new ConflictHttpException('Student already has an active bus route assignment.');
        }

        $assignment = BusRouteAssignment::query()->create([
            'bus_route_id' => $route->id,
            'student_id' => $data['student_id'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'status' => BusRouteAssignment::STATUS_ACTIVE,
        ]);

        $this->audit->record('bus_route_assignment.created', BusRouteAssignment::class, (string) $assignment->id, null, ['bus_route_id' => (string) $route->id]);

        return $assignment->refresh();
    }

    /** @param array{service_date:string,direction:string} $data */
    public function createTrip(BusRoute $route, array $data): BusTrip
    {
        if ($route->status !== BusRoute::STATUS_ACTIVE) {
            throw new ConflictHttpException('Bus route is not active.');
        }

        $trip = BusTrip::query()->create([
            'bus_route_id' => $route->id,
            'service_date' => $data['service_date'],
            'direction' => $data['direction'],
            'status' => BusTrip::STATUS_SCHEDULED,
        ]);

        $this->audit->record('bus_trip.created', BusTrip::class, (string) $trip->id, null, ['bus_route_id' => (string) $route->id]);

        return $trip->refresh();
    }

    /** @param array{latitude:numeric,longitude:numeric,speed_kph?:int|null,recorded_at:string} $data */
    public function ingestTracking(BusTrip $trip, array $data): BusTrackingEvent
    {
        $latest = BusTrackingEvent::query()->where('bus_trip_id', $trip->id)->latest('recorded_at')->first();

        if ($latest !== null && Carbon::parse($data['recorded_at'])->lessThanOrEqualTo(Carbon::parse($latest->recorded_at))) {
            throw new ConflictHttpException('Tracking event must be newer than the latest event.');
        }

        return BusTrackingEvent::query()->create([
            'bus_trip_id' => $trip->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'speed_kph' => $data['speed_kph'] ?? null,
            'recorded_at' => $data['recorded_at'],
        ])->refresh();
    }

    public function latestForParent(Student $student, int $parentCentralUserId): ?BusTrackingEvent
    {
        $owns = StudentParent::query()
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $student->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.central_user_id', $parentCentralUserId)
            ->where('parents.status', 'active')
            ->exists();

        if (! $owns) {
            throw new NotFoundHttpException;
        }

        $routeId = BusRouteAssignment::query()
            ->where('bus_route_assignments.student_id', $student->id)
            ->where('bus_route_assignments.status', BusRouteAssignment::STATUS_ACTIVE)
            ->where('bus_route_assignments.valid_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('bus_route_assignments.valid_until')->orWhere('bus_route_assignments.valid_until', '>=', now()->toDateString());
            })
            ->value('bus_route_assignments.bus_route_id');

        if ($routeId === null) {
            throw new NotFoundHttpException;
        }

        return BusTrackingEvent::query()
            ->join('bus_trips', 'bus_trips.id', '=', 'bus_tracking_events.bus_trip_id')
            ->where('bus_trips.bus_route_id', $routeId)
            ->orderByDesc('bus_tracking_events.recorded_at')
            ->select('bus_tracking_events.*')
            ->first();
    }

    /** @return array<string, mixed> */
    public function liveStatusForParent(Student $student, int $parentCentralUserId): array
    {
        $owns = StudentParent::query()
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $student->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.central_user_id', $parentCentralUserId)
            ->where('parents.status', 'active')
            ->exists();

        if (! $owns) {
            throw new NotFoundHttpException;
        }

        $assignment = BusRouteAssignment::query()
            ->with('route')
            ->where('student_id', $student->id)
            ->where('status', BusRouteAssignment::STATUS_ACTIVE)
            ->where('valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString()))
            ->orderByDesc('valid_from')
            ->first();

        if ($assignment === null || $assignment->route === null) {
            throw new NotFoundHttpException;
        }

        $route = $assignment->route;
        $trip = BusTrip::query()
            ->where('bus_route_id', $route->id)
            ->whereDate('service_date', now()->toDateString())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        $event = $trip === null ? null : BusTrackingEvent::query()
            ->where('bus_trip_id', $trip->id)
            ->latest('recorded_at')
            ->first();

        $lastUpdated = $event?->recorded_at;
        $staleAfter = max(30, (int) config('transport.parent_tracking_stale_seconds', 120));

        return [
            'route' => [
                'id' => (string) $route->id,
                'name' => $route->name,
                'code' => $route->code,
                'status' => $route->status,
            ],
            'bus' => [
                'plate_number' => $route->plate_number,
            ],
            'driver' => [
                'name' => $route->driver_name,
                'phone' => $route->driver_phone,
                'supervisor_name' => $route->supervisor_name,
            ],
            'trip' => $trip === null ? null : [
                'id' => (string) $trip->id,
                'service_date' => $trip->service_date?->toDateString(),
                'direction' => $trip->direction,
                'status' => $trip->status,
                'started_at' => $trip->started_at?->toJSON(),
                'ended_at' => $trip->ended_at?->toJSON(),
            ],
            'latest_position' => $event === null ? null : [
                'id' => (string) $event->id,
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'speed_kph' => $event->speed_kph,
                'heading' => null,
                'recorded_at' => $event->recorded_at?->toJSON(),
            ],
            'estimated_arrival_time' => $route->estimated_arrival_time,
            'eta_minutes' => null,
            'next_stop' => null,
            'last_updated_at' => $lastUpdated?->toJSON(),
            'stale' => $lastUpdated === null || $lastUpdated->lt(now()->subSeconds($staleAfter)),
        ];
    }

    /** @param array{service_date:string,direction:string,reason?:string|null} $data */
    public function optOut(Student $student, int $parentCentralUserId, array $data): BusOptOut
    {
        $parent = Guardian::query()->where('central_user_id', $parentCentralUserId)->where('status', 'active')->first();

        if ($parent === null) {
            throw new NotFoundHttpException;
        }

        $owns = StudentParent::query()
            ->where('student_id', $student->id)
            ->where('parent_id', $parent->id)
            ->where('status', StudentParent::STATUS_ACTIVE)
            ->exists();

        if (! $owns) {
            throw new NotFoundHttpException;
        }

        $routeId = BusRouteAssignment::query()->where('student_id', $student->id)->where('status', BusRouteAssignment::STATUS_ACTIVE)->value('bus_route_id');

        if ($routeId === null) {
            throw new NotFoundHttpException;
        }

        $optOut = BusOptOut::query()->updateOrCreate(
            ['student_id' => $student->id, 'service_date' => $data['service_date'], 'direction' => $data['direction']],
            ['bus_route_id' => $routeId, 'parent_id' => $parent->id, 'reason' => $data['reason'] ?? null],
        );

        $this->audit->record('bus_opt_out.saved', BusOptOut::class, (string) $optOut->id, null, ['student_id' => (string) $student->id]);

        return $optOut->refresh();
    }

    /** @param array{bus_trip_id?:int|null,type:string,message:string} $data */
    public function alert(BusRoute $route, array $data, int $actorCentralUserId): TransportAlert
    {
        $alert = TransportAlert::query()->create([
            'bus_route_id' => $route->id,
            'bus_trip_id' => $data['bus_trip_id'] ?? null,
            'type' => $data['type'],
            'message' => $data['message'],
            'created_by_central_user_id' => $actorCentralUserId,
        ]);

        $recipientIds = BusRouteAssignment::query()
            ->join('student_parent', 'student_parent.student_id', '=', 'bus_route_assignments.student_id')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('bus_route_assignments.bus_route_id', $route->id)
            ->where('bus_route_assignments.status', BusRouteAssignment::STATUS_ACTIVE)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->whereNotNull('parents.central_user_id')
            ->pluck('parents.central_user_id')
            ->map(fn (int $id): int => $id)
            ->unique()
            ->values()
            ->all();

        if ($recipientIds !== []) {
            $this->notifications->create('transport.alert', 'Transport alert', $alert->message, $recipientIds, ['alert_id' => (string) $alert->id], $actorCentralUserId);
        }

        $this->audit->record('transport_alert.created', TransportAlert::class, (string) $alert->id, null, ['recipient_count' => count($recipientIds)]);

        return $alert->refresh();
    }

    private function activeAssignmentCount(BusRoute $route): int
    {
        return BusRouteAssignment::query()
            ->where('bus_route_id', $route->id)
            ->where('status', BusRouteAssignment::STATUS_ACTIVE)
            ->count();
    }
}
