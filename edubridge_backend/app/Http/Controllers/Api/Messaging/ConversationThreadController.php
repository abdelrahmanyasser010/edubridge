<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Actions\Messaging\ConversationMessageManager;
use App\Actions\Messaging\ConversationThreadManager;
use App\Http\Requests\Messaging\SendConversationMessageRequest;
use App\Http\Requests\Messaging\StoreConversationThreadRequest;
use App\Http\Resources\Messaging\ConversationMessageResource;
use App\Http\Resources\Messaging\ConversationThreadResource;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Support\ApiResponse;
use App\Support\IdempotencyResult;
use App\Support\IdempotencyService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConversationThreadController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ConversationThread::class);
        $user = request()->user() ?? throw new AuthenticationException;

        $query = ConversationThread::query()
            ->with('participants')
            ->join('conversation_participants', 'conversation_participants.conversation_thread_id', '=', 'conversation_threads.id')
            ->where('conversation_participants.central_user_id', $user->id)
            ->orderByDesc('conversation_threads.id')
            ->select('conversation_threads.*')
            ->limit(50);

        if (request()->filled('cursor')) {
            $query->where('conversation_threads.id', '<', (int) request('cursor'));
        }

        $threads = $query->get();

        return ApiResponse::data(ConversationThreadResource::collection($threads)->resolve(), meta: [
            'next_cursor' => $threads->count() === 50 ? (string) $threads->last()?->id : null,
        ]);
    }

    public function store(StoreConversationThreadRequest $request, ConversationThreadManager $manager): JsonResponse
    {
        Gate::authorize('create', ConversationThread::class);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new ConversationThreadResource($manager->create(
                creatorCentralUserId: (int) $user->id,
                participantCentralUserId: (int) $request->validated('participant_central_user_id'),
                subject: $request->validated('subject'),
            )))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function messages(int $thread): JsonResponse
    {
        $thread = ConversationThread::query()->findOrFail($thread);
        Gate::authorize('view', $thread);

        return ApiResponse::data(ConversationMessageResource::collection(
            ConversationMessage::query()
                ->with('attachments')
                ->where('conversation_thread_id', $thread->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        )->resolve());
    }

    public function send(SendConversationMessageRequest $request, int $thread, ConversationMessageManager $manager, IdempotencyService $idempotency): JsonResponse
    {
        $thread = ConversationThread::query()->findOrFail($thread);
        Gate::authorize('view', $thread);
        $user = $request->user() ?? throw new AuthenticationException;

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['The Idempotency-Key header is required.'],
            ]);
        }

        $result = $idempotency->run(
            clientKey: $key,
            operation: 'conversation.send.'.$thread->id,
            payload: $request->validated(),
            callback: fn (): IdempotencyResult => new IdempotencyResult(
                payload: (new ConversationMessageResource($manager->send($thread, (int) $user->id, $request->validated())))->resolve($request),
                status: 201,
                replayed: false,
            ),
            actorCentralUserId: (int) $user->id,
        );

        return ApiResponse::data($result->payload, $result->status, [
            'idempotency_replayed' => $result->replayed,
        ]);
    }

    public function markRead(int $thread, ConversationMessageManager $manager): JsonResponse
    {
        $thread = ConversationThread::query()->findOrFail($thread);
        Gate::authorize('view', $thread);
        $user = request()->user() ?? throw new AuthenticationException;

        $manager->markRead($thread, (int) $user->id);

        return ApiResponse::data(['read' => true]);
    }
}
