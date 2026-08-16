<?php

namespace App\Policies;

use App\Models\LeavePermit;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class LeavePermitPolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active();
    }

    public function review(User $user, LeavePermit $permit): bool
    {
        return app(TenantContext::class)->active()
            && $permit->status === LeavePermit::STATUS_PENDING
            && Gate::forUser($user)->allows('operations.leave_review');
    }

    public function useToken(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('operations.leave_review');
    }
}
