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

        return DB::connection('tenant')
            ->table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('user_roles.central_user_id', $user->id)
            ->where('permissions.key', $permission)
            ->where(function ($query) {
                $query->whereNull('user_roles.valid_from')->orWhere('user_roles.valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('user_roles.valid_until')->orWhere('user_roles.valid_until', '>', now());
            })
            ->exists();
    }
}
