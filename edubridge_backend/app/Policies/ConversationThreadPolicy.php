<?php

namespace App\Policies;

use App\Models\ConversationThread;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConversationThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('message.view');
    }

    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('message.send');
    }

    public function view(User $user, ConversationThread $thread): bool
    {
        return app(TenantContext::class)->active()
            && DB::connection('tenant')->table('conversation_participants')
                ->where('conversation_thread_id', $thread->id)
                ->where('central_user_id', $user->id)
                ->exists();
    }
}
