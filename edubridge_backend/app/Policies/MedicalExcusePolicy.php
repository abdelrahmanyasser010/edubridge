<?php

namespace App\Policies;

use App\Models\MedicalExcuse;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class MedicalExcusePolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('attendance.view');
    }

    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->active()
            && (Gate::forUser($user)->allows('operations.view')
                || Gate::forUser($user)->allows('attendance.review_excuse'));
    }

    public function review(User $user, MedicalExcuse $excuse): bool
    {
        return app(TenantContext::class)->active()
            && $excuse->exists
            && Gate::forUser($user)->allows('attendance.review_excuse');
    }
}
