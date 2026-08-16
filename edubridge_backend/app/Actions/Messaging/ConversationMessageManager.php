<?php

namespace App\Actions\Messaging;

use App\Actions\Notifications\NotificationManager;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Models\FileObject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationMessageManager
{
    public function __construct(private readonly NotificationManager $notifications) {}

    /**
     * @param  array{body?:string|null,attachment_file_ids?:list<int>}  $data
     */
    public function send(ConversationThread $thread, int $senderCentralUserId, array $data): ConversationMessage
    {
        return DB::connection('tenant')->transaction(function () use ($thread, $senderCentralUserId, $data): ConversationMessage {
            $this->ensureUsableFiles($senderCentralUserId, $data['attachment_file_ids'] ?? []);

            $message = ConversationMessage::query()->create([
                'conversation_thread_id' => $thread->id,
                'sender_central_user_id' => $senderCentralUserId,
                'body' => $data['body'] ?? null,
            ]);

            foreach ($data['attachment_file_ids'] ?? [] as $fileId) {
                $message->attachments()->create(['file_id' => $fileId]);
            }

            $recipients = DB::connection('tenant')->table('conversation_participants')
                ->where('conversation_thread_id', $thread->id)
                ->where('central_user_id', '!=', $senderCentralUserId)
                ->pluck('central_user_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($recipients !== []) {
                $this->notifications->create(
                    type: 'message.received',
                    title: 'New message',
                    body: $message->body,
                    recipientCentralUserIds: $recipients,
                    data: [
                        'conversation_thread_id' => (string) $thread->id,
                        'message_id' => (string) $message->id,
                    ],
                    actorCentralUserId: $senderCentralUserId,
                );
            }

            return $message->load('attachments');
        });
    }

    public function markRead(ConversationThread $thread, int $centralUserId): void
    {
        DB::connection('tenant')->table('conversation_participants')
            ->where('conversation_thread_id', $thread->id)
            ->where('central_user_id', $centralUserId)
            ->update(['last_read_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<int> $fileIds */
    private function ensureUsableFiles(int $senderCentralUserId, array $fileIds): void
    {
        if ($fileIds === []) {
            return;
        }

        $validCount = FileObject::query()
            ->whereIn('id', $fileIds)
            ->where('owner_central_user_id', $senderCentralUserId)
            ->where('scan_status', FileObject::SCAN_CLEAN)
            ->count();

        if ($validCount !== count($fileIds)) {
            throw ValidationException::withMessages([
                'attachment_file_ids' => ['Every attachment must belong to the sender and pass scanning first.'],
            ]);
        }
    }
}
