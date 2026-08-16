<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('attendance.view');
    }
}
