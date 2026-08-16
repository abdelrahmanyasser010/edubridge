<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Requests\Dashboard\Broadcasts\StoreMessageTemplateRequest;
use App\Http\Requests\Dashboard\Broadcasts\UpdateMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MessageTemplateController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('broadcasts.view');
        $templates = MessageTemplate::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get();

        return ApiResponse::data($templates->map(fn (MessageTemplate $t) => $this->item($t))->all());
    }

    public function store(StoreMessageTemplateRequest $request, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('broadcasts.send');
        $user = $request->user() ?? throw new AuthenticationException;
        $template = MessageTemplate::query()->create($request->validated() + [
            'created_by_central_user_id' => (int) $user->id,
            'updated_by_central_user_id' => (int) $user->id,
        ]);
        $audit->record('message_template.created', MessageTemplate::class, (string) $template->id, null, $template->only(['name', 'title', 'type', 'default_target_type', 'is_active']));

        return ApiResponse::data($this->item($template), Response::HTTP_CREATED);
    }

    public function update(UpdateMessageTemplateRequest $request, int $template, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('broadcasts.send');
        $user = $request->user() ?? throw new AuthenticationException;
        $model = MessageTemplate::query()->findOrFail($template);
        $before = $model->only(['name', 'title', 'body', 'type', 'default_target_type', 'is_active']);
        $model->fill($request->validated());
        $model->updated_by_central_user_id = (int) $user->id;
        $model->save();
        $audit->record('message_template.updated', MessageTemplate::class, (string) $model->id, $before, $model->only(['name', 'title', 'body', 'type', 'default_target_type', 'is_active']));

        return ApiResponse::data($this->item($model->refresh()));
    }

    public function destroy(int $template, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('broadcasts.send');
        $model = MessageTemplate::query()->findOrFail($template);
        $before = $model->only(['name', 'title', 'type', 'default_target_type', 'is_active']);
        $model->delete();
        $audit->record('message_template.deleted', MessageTemplate::class, (string) $template, $before, null);

        return ApiResponse::data(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string,mixed> */
    private function item(MessageTemplate $t): array
    {
        return [
            'id' => (string) $t->id, 'name' => $t->name, 'title' => $t->title, 'body' => $t->body,
            'type' => $t->type, 'default_target_type' => $t->default_target_type,
            'is_active' => (bool) $t->is_active, 'updated_at' => $t->updated_at?->toJSON(),
        ];
    }
}
