<?php

namespace App\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class PermissionChecker
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function isKnownPermission(string $permission): bool
    {
        return in_array($permission, PermissionCatalog::keys(), true);
    }

    public function allows(User $user, string $permission): bool
    {
        if (! $this->isKnownPermission($permission) || ! $this->tenantContext->active()) {
            return false;
        }

        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || (int) $token->school_id !== $this->tenantContext->schoolId()) {
            return false;
        }

        $schoolId = $this->tenantContext->schoolId();
        $now = now();

        $membership = DB::connection('central')->table('school_user')
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $now);
            })
            ->first(['role_key']);

        if ($membership === null || empty($membership->role_key)) {
            return false;
        }

        return DB::connection('tenant')
            ->table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('user_roles.central_user_id', $user->id)
            ->where('roles.key', $membership->role_key)
            ->where('permissions.key', $permission)
            ->where(function ($query) use ($now) {
                $query->whereNull('user_roles.valid_from')->orWhere('user_roles.valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('user_roles.valid_until')->orWhere('user_roles.valid_until', '>', $now);
            })
            ->exists();
    }
}
