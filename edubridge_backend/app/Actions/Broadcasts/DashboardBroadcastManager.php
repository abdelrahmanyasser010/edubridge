<?php

namespace App\Actions\Broadcasts;

use App\Models\BroadcastMessage;
use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use App\Models\StudentParent;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Outbox;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DashboardBroadcastManager
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function broadcasts(array $filters): LengthAwarePaginator
    {
        $paginator = BroadcastMessage::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, mixed $type) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        /** @var Collection<int, BroadcastMessage> $items */
        $items = collect($paginator->items());
        $creators = $this->creatorMap($items);

        return $paginator->through(fn (BroadcastMessage $broadcast): array => $this->item($broadcast, $creators));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorCentralUserId): array
    {
        $target = $data['target'];
        $scheduledAt = isset($data['scheduled_at']) ? Carbon::parse((string) $data['scheduled_at']) : null;
        $status = $scheduledAt === null ? BroadcastMessage::STATUS_DRAFT : BroadcastMessage::STATUS_SCHEDULED;
        $broadcast = BroadcastMessage::query()->create([
            'target' => $target['type'],
            'target_ids' => array_values($target['ids'] ?? []),
            'channels' => array_values(array_unique($data['channels'])),
            'priority' => $data['priority'] ?? 'normal',
            'status' => $status,
            'title' => $data['title'],
            'body' => $data['body'],
            'type' => $data['type'],
            'scheduled_at' => $scheduledAt,
            'created_by_central_user_id' => $actorCentralUserId,
        ]);

        if ($scheduledAt !== null) {
            $this->outbox->publishAfterCommit('broadcast.dispatch_due', ['broadcast_id' => (string) $broadcast->id], $scheduledAt);
        }

        $this->audit->record('broadcast.created', BroadcastMessage::class, (string) $broadcast->id, null, [
            'target' => $broadcast->target,
            'status' => $broadcast->status,
            'scheduled_at' => $scheduledAt?->toISOString(),
        ]);

        return $this->broadcast($broadcast);
    }

    /** @return array<string, mixed> */
    public function broadcast(BroadcastMessage $broadcast): array
    {
        return $this->item($broadcast, $this->creatorMap(collect([$broadcast])));
    }

    public function send(BroadcastMessage $broadcast, int $actorCentralUserId): array
    {
        if ($broadcast->status === BroadcastMessage::STATUS_CANCELLED) {
            throw new HttpException(409, 'Cancelled broadcasts cannot be sent.');
        }

        if ($broadcast->status === BroadcastMessage::STATUS_SENT) {
            return $this->broadcast($broadcast);
        }

        $scheduledAt = $broadcast->scheduled_at === null ? null : Carbon::parse($broadcast->scheduled_at);
        if ($scheduledAt !== null && $scheduledAt->isFuture()) {
            $broadcast->forceFill(['status' => BroadcastMessage::STATUS_SCHEDULED])->save();
            $this->outbox->publishAfterCommit('broadcast.dispatch_due', ['broadcast_id' => (string) $broadcast->id], $scheduledAt);
            $this->audit->record('broadcast.scheduled', BroadcastMessage::class, (string) $broadcast->id, null, ['scheduled_at' => $scheduledAt->toISOString()]);

            return $this->broadcast($broadcast->refresh());
        }

        $recipients = $this->targetRecipients($broadcast);
        $notification = $this->createNotification(
            'broadcast.'.$broadcast->type,
            $broadcast->title,
            $broadcast->body,
            $recipients,
            ['broadcast_id' => (string) $broadcast->id, 'priority' => $broadcast->priority, 'channels' => $broadcast->channels ?? []],
            $actorCentralUserId,
            $broadcast->channels ?? ['database', 'push'],
        );

        $broadcast->forceFill([
            'status' => BroadcastMessage::STATUS_SENT,
            'notification_id' => $notification->id,
            'sent_at' => now(),
        ])->save();

        $this->audit->record('broadcast.sent', BroadcastMessage::class, (string) $broadcast->id, null, [
            'recipient_count' => count($recipients),
            'notification_id' => (string) $notification->id,
        ]);

        return $this->broadcast($broadcast->refresh());
    }

    public function cancel(BroadcastMessage $broadcast, int $actorCentralUserId): array
    {
        if ($broadcast->status === BroadcastMessage::STATUS_SENT) {
            throw new HttpException(409, 'Sent broadcasts cannot be cancelled.');
        }

        $before = ['status' => $broadcast->status];
        $broadcast->forceFill([
            'status' => BroadcastMessage::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_central_user_id' => $actorCentralUserId,
        ])->save();

        $this->audit->record('broadcast.cancelled', BroadcastMessage::class, (string) $broadcast->id, $before, ['status' => BroadcastMessage::STATUS_CANCELLED]);

        return $this->broadcast($broadcast->refresh());
    }

    /** @return array<string, int> */
    public function deliveries(BroadcastMessage $broadcast): array
    {
        if ($broadcast->notification_id === null) {
            $queued = in_array($broadcast->status, [BroadcastMessage::STATUS_DRAFT, BroadcastMessage::STATUS_SCHEDULED], true)
                ? count($this->targetRecipients($broadcast))
                : 0;

            return ['queued' => $queued, 'sent' => 0, 'failed' => 0, 'read' => 0];
        }

        $counts = DB::connection('tenant')
            ->table('notification_deliveries')
            ->where('notification_id', $broadcast->notification_id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'queued' => (int) ($counts->get(NotificationDelivery::STATUS_PENDING) ?? 0),
            'sent' => (int) ($counts->get(NotificationDelivery::STATUS_DELIVERED) ?? 0),
            'failed' => (int) ($counts->get('failed') ?? 0),
            'read' => (int) ($counts->get(NotificationDelivery::STATUS_READ) ?? 0),
        ];
    }

    /**
     * @param  list<int>  $recipientCentralUserIds
     * @param  array<string, mixed>  $data
     * @param  list<string>  $channels
     */
    private function createNotification(string $type, string $title, ?string $body, array $recipientCentralUserIds, array $data, int $actorCentralUserId, array $channels): NotificationMessage
    {
        return DB::connection('tenant')->transaction(function () use ($type, $title, $body, $recipientCentralUserIds, $data, $actorCentralUserId, $channels): NotificationMessage {
            $notification = NotificationMessage::query()->create([
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'actor_central_user_id' => $actorCentralUserId,
            ]);

            $pushDeliveryIds = [];
            $smsDeliveryIds = [];

            foreach (array_values(array_unique($recipientCentralUserIds)) as $recipientId) {
                foreach (array_values(array_unique($channels)) as $channel) {
                    $delivery = NotificationDelivery::query()->create([
                        'notification_id' => $notification->id,
                        'central_user_id' => $recipientId,
                        'channel' => $channel,
                        'status' => $channel === NotificationDelivery::CHANNEL_DATABASE ? NotificationDelivery::STATUS_DELIVERED : NotificationDelivery::STATUS_PENDING,
                        'delivered_at' => $channel === NotificationDelivery::CHANNEL_DATABASE ? now() : null,
                    ]);

                    if ($channel === NotificationDelivery::CHANNEL_PUSH) {
                        $pushDeliveryIds[] = (string) $delivery->id;
                    }

                    if ($channel === 'sms') {
                        $smsDeliveryIds[] = (string) $delivery->id;
                    }
                }
            }

            if ($pushDeliveryIds !== []) {
                $this->outbox->publishAfterCommit('notification.push_requested', [
                    'notification_id' => (string) $notification->id,
                    'delivery_ids' => $pushDeliveryIds,
                ]);
            }

            if ($smsDeliveryIds !== []) {
                $this->outbox->publishAfterCommit('notification.sms_requested', [
                    'notification_id' => (string) $notification->id,
                    'delivery_ids' => $smsDeliveryIds,
                ]);
            }

            return $notification;
        });
    }

    /**
     * @param  array<int, array{id: string, name: string, email: string}>  $creators
     * @return array<string, mixed>
     */
    private function item(BroadcastMessage $broadcast, array $creators): array
    {
        $creatorId = $broadcast->created_by_central_user_id;

        return [
            'id' => (string) $broadcast->id,
            'title' => $broadcast->title,
            'body' => $broadcast->body,
            'type' => $broadcast->type,
            'target' => [
                'type' => $broadcast->target,
                'ids' => $broadcast->target_ids ?? [],
            ],
            'target_label' => $this->targetLabel($broadcast),
            'channels' => $broadcast->channels ?? ['database', 'push'],
            'priority' => $broadcast->priority,
            'status' => $broadcast->status,
            'scheduled_at' => $broadcast->scheduled_at === null ? null : Carbon::parse($broadcast->scheduled_at)->toISOString(),
            'sent_at' => $broadcast->sent_at === null ? null : Carbon::parse($broadcast->sent_at)->toISOString(),
            'cancelled_at' => $broadcast->cancelled_at === null ? null : Carbon::parse($broadcast->cancelled_at)->toISOString(),
            'created_by' => $creators[$creatorId] ?? ['id' => (string) $creatorId, 'name' => null, 'email' => null],
            'reach_count' => $broadcast->status === BroadcastMessage::STATUS_SENT && $broadcast->notification_id !== null
                ? (int) DB::connection('tenant')->table('notification_deliveries')->where('notification_id', $broadcast->notification_id)->distinct('central_user_id')->count('central_user_id')
                : count($this->targetRecipients($broadcast)),
        ];
    }

    /** @param Collection<int, BroadcastMessage> $broadcasts */
    private function creatorMap(Collection $broadcasts): array
    {
        $creatorIds = $broadcasts
            ->pluck('created_by_central_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', $creatorIds)->get(['id', 'name', 'email']);

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

    /** @return list<int> */
    private function targetRecipients(BroadcastMessage $broadcast): array
    {
        $ids = collect($broadcast->target_ids ?? [])->map(fn (mixed $id): string => (string) $id)->all();

        $query = match ($broadcast->target) {
            'all' => DB::connection('tenant')->table('user_roles'),
            'roles' => DB::connection('tenant')->table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->whereIn('roles.key', $ids),
            'teachers' => DB::connection('tenant')->table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('roles.key', 'teacher'),
            'parents' => DB::connection('tenant')->table('parents')->whereNotNull('central_user_id'),
            'students' => DB::connection('tenant')->table('students')->whereNotNull('central_user_id'),
            'custom_users' => null,
            'section' => null,
            'grade_level' => null,
            default => null,
        };

        if ($broadcast->target === 'custom_users') {
            return collect($ids)->map(fn (string $id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->unique()->values()->all();
        }

        if ($broadcast->target === 'section' || $broadcast->target === 'grade_level') {
            return $this->studentAndParentRecipients($broadcast->target, $ids);
        }

        if ($query === null) {
            return [];
        }

        $column = in_array($broadcast->target, ['parents', 'students'], true) ? 'central_user_id' : 'central_user_id';

        return $query->pluck($column)->map(fn (mixed $id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->unique()->values()->all();
    }

    /** @param list<string> $ids */
    private function studentAndParentRecipients(string $target, array $ids): array
    {
        $students = DB::connection('tenant')->table('students')
            ->when($target === 'section', fn ($query) => $query->whereIn('section_id', $ids))
            ->when($target === 'grade_level', fn ($query) => $query->whereIn('grade_level_id', $ids))
            ->get(['id', 'central_user_id']);

        $studentIds = $students->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $studentUsers = $students->pluck('central_user_id')->map(fn (mixed $id): int => (int) $id)->filter(fn (int $id): bool => $id > 0);
        $parentUsers = DB::connection('tenant')->table('student_parent')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->whereIn('student_parent.student_id', $studentIds)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->whereNotNull('parents.central_user_id')
            ->pluck('parents.central_user_id')
            ->map(fn (mixed $id): int => (int) $id);

        return $studentUsers->merge($parentUsers)->unique()->values()->all();
    }

    private function targetLabel(BroadcastMessage $broadcast): string
    {
        $ids = $broadcast->target_ids ?? [];

        return $broadcast->target === 'all'
            ? 'All school users'
            : str_replace('_', ' ', $broadcast->target).' ('.count($ids).')';
    }
}
