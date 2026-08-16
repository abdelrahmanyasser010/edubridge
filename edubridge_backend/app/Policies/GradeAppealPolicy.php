<?php

namespace App\Policies;

use App\Models\GradeAppeal;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class GradeAppealPolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('grade.view');
    }

    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('grade.appeal_review');
    }

    public function review(User $user, GradeAppeal $appeal): bool
    {
        return app(TenantContext::class)->active()
            && $appeal->status === GradeAppeal::STATUS_OPEN
            && Gate::forUser($user)->allows('grade.appeal_review');
    }

    public function correct(User $user, GradeAppeal $appeal): bool
    {
        return app(TenantContext::class)->active()
            && $appeal->status === GradeAppeal::STATUS_APPROVED
            && Gate::forUser($user)->allows('grade.appeal_review');
    }
}
