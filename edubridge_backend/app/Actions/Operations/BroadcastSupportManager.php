<?php

namespace App\Actions\Operations;

use App\Actions\Notifications\NotificationManager;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

class BroadcastSupportManager
{
    public function __construct(
        private readonly NotificationManager $notifications,
        private readonly Outbox $outbox,
    ) {}

    public function broadcast(string $target, string $title, ?string $body, ?\DateTimeInterface $scheduledAt, int $actorCentralUserId): int
    {
        $id = (int) DB::connection('tenant')->table('broadcast_messages')->insertGetId([
            'target' => $target,
            'title' => $title,
            'body' => $body,
            'scheduled_at' => $scheduledAt,
            'created_by_central_user_id' => $actorCentralUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recipients = $this->targetRecipients($target);
        if ($scheduledAt === null) {
            $this->notifications->create('broadcast.message', $title, $body, $recipients, ['broadcast_id' => (string) $id], $actorCentralUserId);
        } else {
            $this->outbox->publishAfterCommit('broadcast.dispatch_due', ['broadcast_id' => (string) $id], $scheduledAt);
        }

        return $id;
    }

    public function openTicket(int $userId, string $subject, string $body): int
    {
        $ticketId = (int) DB::connection('tenant')->table('support_tickets')->insertGetId(['opened_by_central_user_id' => $userId, 'subject' => $subject, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->reply($ticketId, $userId, $body);

        return $ticketId;
    }

    public function reply(int $ticketId, int $userId, string $body): int
    {
        return (int) DB::connection('tenant')->table('support_ticket_replies')->insertGetId(['support_ticket_id' => $ticketId, 'author_central_user_id' => $userId, 'body' => $body, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return list<int> */
    private function targetRecipients(string $target): array
    {
        $query = DB::connection('tenant')->table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id');
        if ($target !== 'all') {
            $query->where('roles.key', $target);
        }

        return $query->pluck('user_roles.central_user_id')->map(fn (int $id): int => $id)->unique()->values()->all();
    }
}
