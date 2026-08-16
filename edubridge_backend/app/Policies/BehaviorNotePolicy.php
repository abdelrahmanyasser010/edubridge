<?php

namespace App\Policies;

use App\Models\BehaviorNote;
use App\Models\StudentParent;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BehaviorNotePolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('behavior.create');
    }

    public function review(User $user, BehaviorNote $note): bool
    {
        return app(TenantContext::class)->active()
            && $note->status === BehaviorNote::STATUS_PENDING_REVIEW
            && Gate::forUser($user)->allows('behavior.publish');
    }

    public function acknowledge(User $user, BehaviorNote $note): bool
    {
        return app(TenantContext::class)->active()
            && $note->status === BehaviorNote::STATUS_PUBLISHED
            && Gate::forUser($user)->allows('behavior.acknowledge')
            && DB::connection('tenant')->table('student_parent')
                ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
                ->where('student_parent.student_id', $note->student_id)
                ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
                ->where('parents.central_user_id', $user->id)
                ->where('parents.status', 'active')
                ->exists();
    }

    public function resolve(User $user, BehaviorNote $note): bool
    {
        return app(TenantContext::class)->active()
            && in_array($note->status, [BehaviorNote::STATUS_PUBLISHED, BehaviorNote::STATUS_ACKNOWLEDGED], true)
            && Gate::forUser($user)->allows('behavior.resolve');
    }

    public function recommend(User $user, BehaviorNote $note): bool
    {
        return app(TenantContext::class)->active()
            && in_array($note->status, [BehaviorNote::STATUS_PUBLISHED, BehaviorNote::STATUS_ACKNOWLEDGED, BehaviorNote::STATUS_RESOLVED], true)
            && Gate::forUser($user)->allows('behavior.review');
    }
}
