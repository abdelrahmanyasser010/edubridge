<?php

namespace App\Actions\Mobile;

use App\Actions\Notifications\NotificationManager;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportTicketManager
{
    public function __construct(private readonly NotificationManager $notifications) {}

    /** @return list<array{key:string,label:string}> */
    public function categories(): array
    {
        return [
            ['key' => 'general', 'label' => 'General'],
            ['key' => 'technical', 'label' => 'Technical'],
            ['key' => 'academic', 'label' => 'Academic'],
            ['key' => 'attendance', 'label' => 'Attendance'],
            ['key' => 'transport', 'label' => 'Transport'],
            ['key' => 'finance', 'label' => 'Finance & Payments'],
        ];
    }

    /** @return LengthAwarePaginator<int, SupportTicket> */
    public function list(int $centralUserId, int $perPage = 20): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->where('opened_by_central_user_id', $centralUserId)
            ->withCount('replies')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function show(int $ticketId, int $centralUserId): SupportTicket
    {
        return SupportTicket::query()
            ->where('id', $ticketId)
            ->where('opened_by_central_user_id', $centralUserId)
            ->with('replies')
            ->first() ?? throw new NotFoundHttpException;
    }

    public function create(int $centralUserId, string $categoryKey, string $subject, string $message): SupportTicket
    {
        $this->ensureCategory($categoryKey);

        $ticket = DB::connection('tenant')->transaction(function () use ($centralUserId, $categoryKey, $subject, $message): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'opened_by_central_user_id' => $centralUserId,
                'category_key' => $categoryKey,
                'subject' => $subject,
                'status' => SupportTicket::STATUS_OPEN,
            ]);

            SupportTicketReply::query()->create([
                'support_ticket_id' => $ticket->id,
                'author_central_user_id' => $centralUserId,
                'body' => $message,
            ]);

            return $ticket->refresh()->load('replies');
        });

        $adminIds = DB::connection('tenant')->table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.key', 'school_admin')
            ->pluck('user_roles.central_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        if ($adminIds !== []) {
            $this->notifications->create('support.ticket.opened', 'New support ticket', $ticket->subject, $adminIds, ['ticket_id' => (string) $ticket->id], $centralUserId);
        }

        return $ticket;
    }

    public function reply(int $ticketId, int $centralUserId, string $message): SupportTicket
    {
        return DB::connection('tenant')->transaction(function () use ($ticketId, $centralUserId, $message): SupportTicket {
            $ticket = SupportTicket::query()
                ->where('id', $ticketId)
                ->where('opened_by_central_user_id', $centralUserId)
                ->lockForUpdate()
                ->first() ?? throw new NotFoundHttpException;

            if ($ticket->status === SupportTicket::STATUS_CLOSED) {
                throw new ConflictHttpException('Closed support tickets cannot receive new messages.');
            }

            SupportTicketReply::query()->create([
                'support_ticket_id' => $ticket->id,
                'author_central_user_id' => $centralUserId,
                'body' => $message,
            ]);

            if (in_array($ticket->status, [SupportTicket::STATUS_ANSWERED, SupportTicket::STATUS_RESOLVED], true)) {
                $ticket->forceFill(['status' => SupportTicket::STATUS_PENDING, 'resolved_at' => null])->save();
            } else {
                $ticket->touch();
            }

            return $ticket->refresh()->load('replies');
        });
    }

    /** @return LengthAwarePaginator<int, SupportTicket> */
    public function adminList(array $filters): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->when($filters['category_key'] ?? null, fn ($query, mixed $category) => $query->where('category_key', $category))
            ->withCount('replies')
            ->orderByDesc('updated_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function adminShow(int $ticketId): SupportTicket
    {
        return SupportTicket::query()->with('replies')->findOrFail($ticketId);
    }

    public function adminReply(int $ticketId, int $actorCentralUserId, string $message): SupportTicket
    {
        $ticket = DB::connection('tenant')->transaction(function () use ($ticketId, $actorCentralUserId, $message): SupportTicket {
            $ticket = SupportTicket::query()->whereKey($ticketId)->lockForUpdate()->firstOrFail();
            if ($ticket->status === SupportTicket::STATUS_CLOSED) {
                throw new ConflictHttpException('Closed support tickets cannot receive new messages.');
            }

            SupportTicketReply::query()->create([
                'support_ticket_id' => $ticket->id,
                'author_central_user_id' => $actorCentralUserId,
                'body' => $message,
            ]);
            $ticket->forceFill(['status' => SupportTicket::STATUS_ANSWERED, 'resolved_at' => null])->save();

            return $ticket->refresh()->load('replies');
        });

        $this->notifications->create(
            'support.ticket.answered',
            'Support ticket updated',
            $ticket->subject,
            [(int) $ticket->opened_by_central_user_id],
            ['ticket_id' => (string) $ticket->id],
            $actorCentralUserId,
        );

        return $ticket;
    }

    public function adminUpdateStatus(int $ticketId, string $status): SupportTicket
    {
        return DB::connection('tenant')->transaction(function () use ($ticketId, $status): SupportTicket {
            $ticket = SupportTicket::query()->whereKey($ticketId)->lockForUpdate()->firstOrFail();
            $ticket->forceFill([
                'status' => $status,
                'resolved_at' => $status === SupportTicket::STATUS_RESOLVED ? now() : null,
                'closed_at' => $status === SupportTicket::STATUS_CLOSED ? now() : null,
            ])->save();

            return $ticket->refresh()->load('replies');
        });
    }

    private function ensureCategory(string $key): void
    {
        if (! in_array($key, array_column($this->categories(), 'key'), true)) {
            throw new ConflictHttpException('Unsupported support ticket category.');
        }
    }
}
