<?php

namespace App\Actions\Rbac;

use App\Auth\ApplicationAccessMatrix;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class DashboardRbacManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
        private readonly TenantUserRoleSynchronizer $synchronizer,
    ) {}

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->rolesWithPermissions()->values()->all();
    }

    /** @param array<string, mixed> $data */
    public function createRole(array $data): array
    {
        $permissions = $this->validatedPermissionKeys($data['permissions'] ?? []);
        $now = now();

        if (DB::connection('tenant')->table('roles')->where('key', $data['key'])->exists()) {
            throw ValidationException::withMessages(['key' => ['Role key already exists.']]);
        }

        $roleId = DB::connection('tenant')->table('roles')->insertGetId([
            'key' => $data['key'],
            'name' => json_encode(['en' => $data['name']], JSON_THROW_ON_ERROR),
            'is_system' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->syncRolePermissions((int) $roleId, $permissions);
        $this->audit->record('rbac.role.created', 'role', (string) $roleId, null, [
            'key' => $data['key'],
            'permissions' => $permissions,
        ]);

        return $this->roleItem((object) [
            'id' => $roleId,
            'key' => $data['key'],
            'name' => json_encode(['en' => $data['name']], JSON_THROW_ON_ERROR),
            'is_system' => false,
        ], collect($permissions));
    }

    /** @return list<array<string, mixed>> */
    public function permissions(): array
    {
        return DB::connection('tenant')
            ->table('permissions')
            ->orderBy('key')
            ->get(['id', 'key', 'description'])
            ->map(fn (object $permission): array => [
                'id' => (string) $permission->id,
                'key' => (string) $permission->key,
                'label' => $this->localizedJson($permission->description),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function matrix(): array
    {
        $permissionKeys = $this->permissionKeys();
        $roles = $this->rolesWithPermissions()
            ->map(fn (array $role): array => [
                'key' => $role['key'],
                'label' => $role['label'],
                'is_system' => $role['is_system'],
                'permissions' => collect($permissionKeys)
                    ->mapWithKeys(fn (string $permission): array => [$permission => in_array($permission, $role['permissions'], true)])
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'permissions' => $permissionKeys,
            'roles' => $roles,
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateMatrix(array $data): array
    {
        $rolesByKey = DB::connection('tenant')->table('roles')->pluck('id', 'key');

        foreach ($data['roles'] as $roleUpdate) {
            $roleKey = (string) $roleUpdate['key'];
            if (! $rolesByKey->has($roleKey)) {
                throw ValidationException::withMessages(['roles' => ['Unknown role key: '.$roleKey]]);
            }

            $permissions = $this->validatedPermissionKeys($roleUpdate['permissions']);
            if ($roleKey === 'school_admin' && (! in_array('rbac.view', $permissions, true) || ! in_array('rbac.manage', $permissions, true))) {
                throw ValidationException::withMessages([
                    'roles' => ['The school_admin role must retain RBAC view/manage permissions to prevent administrative lockout.'],
                ]);
            }
            $this->syncRolePermissions((int) $rolesByKey->get($roleKey), $permissions);
        }

        $this->audit->record('rbac.matrix.updated', 'rbac_matrix', null, null, [
            'roles' => collect($data['roles'])->map(fn (array $role): string => (string) $role['key'])->all(),
        ]);

        return $this->matrix();
    }

    /** @return list<array<string, mixed>> */
    public function adminAccounts(): array
    {
        $schoolId = $this->tenantContext->schoolId();
        $rows = DB::connection('central')
            ->table('school_user')
            ->join('users', 'users.id', '=', 'school_user.user_id')
            ->where('school_user.school_id', $schoolId)
            ->whereIn('school_user.role_key', ApplicationAccessMatrix::rolesFor('dashboard'))
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'school_user.role_key',
                'school_user.status',
            ]);

        return $this->adminAccountItems($rows);
    }

    /** @param array<string, mixed> $data */
    public function createAdminAccount(array $data): array
    {
        $role = $this->dashboardRole((string) $data['role_key']);
        $user = User::query()->firstOrNew(['email' => $data['email']]);
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => 'active',
        ]);

        if (! $user->exists || isset($data['password'])) {
            $user->password = (string) ($data['password'] ?? str()->password(32));
        }

        $user->save();

        $schoolId = $this->tenantContext->schoolId();

        DB::connection('central')->table('school_user')->updateOrInsert(
            ['school_id' => $schoolId, 'user_id' => $user->id],
            [
                'role_key' => $role['key'],
                'status' => $data['status'] ?? 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->synchronizer->syncUser($schoolId, (int) $user->id);
        $this->audit->record('rbac.admin_account.created', 'central_user', (string) $user->id, null, [
            'role_key' => $role['key'],
            'status' => $data['status'] ?? 'active',
        ]);

        return $this->adminAccount((int) $user->id);
    }

    public function updateAdminRole(int $centralUserId, string $roleKey): array
    {
        $role = $this->dashboardRole($roleKey);
        $this->ensureSchoolMembership($centralUserId);

        $schoolId = $this->tenantContext->schoolId();

        $beforeRole = DB::connection('central')
            ->table('school_user')
            ->where('school_id', $schoolId)
            ->where('user_id', $centralUserId)
            ->value('role_key');

        if ($beforeRole === 'school_admin' && $role['key'] !== 'school_admin') {
            $this->ensureAnotherActiveSchoolAdmin($centralUserId);
        }

        DB::connection('central')
            ->table('school_user')
            ->where('school_id', $schoolId)
            ->where('user_id', $centralUserId)
            ->update(['role_key' => $role['key'], 'updated_at' => now()]);

        $this->synchronizer->syncUser($schoolId, $centralUserId);
        $this->audit->record('rbac.admin_account.role_updated', 'central_user', (string) $centralUserId, ['role_key' => $beforeRole], ['role_key' => $role['key']]);

        return $this->adminAccount($centralUserId);
    }

    public function updateAdminStatus(int $centralUserId, string $status): array
    {
        $this->ensureSchoolMembership($centralUserId);
        $schoolId = $this->tenantContext->schoolId();

        $membership = DB::connection('central')
            ->table('school_user')
            ->where('school_id', $schoolId)
            ->where('user_id', $centralUserId)
            ->first(['status', 'role_key']);
        $beforeStatus = $membership?->status;

        if ($membership?->role_key === 'school_admin' && $status !== 'active') {
            $this->ensureAnotherActiveSchoolAdmin($centralUserId);
        }

        DB::connection('central')
            ->table('school_user')
            ->where('school_id', $schoolId)
            ->where('user_id', $centralUserId)
            ->update(['status' => $status, 'updated_at' => now()]);

        $this->synchronizer->syncUser($schoolId, $centralUserId);

        if ($status !== 'active') {
            PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $centralUserId)
                ->where('school_id', $schoolId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        $this->audit->record('rbac.admin_account.status_updated', 'central_user', (string) $centralUserId, ['status' => $beforeStatus], ['status' => $status]);

        return $this->adminAccount($centralUserId);
    }

    /**
     * @return Collection<string, array{id: string, key: string, label: string, is_system: bool, permissions: list<string>}>
     */
    private function rolesWithPermissions(): Collection
    {
        $permissionsByRole = DB::connection('tenant')
            ->table('permission_role')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->get(['roles.key as role_key', 'permissions.key as permission_key'])
            ->groupBy('role_key')
            ->map(fn (Collection $rows): array => $rows->pluck('permission_key')->map(fn (mixed $key): string => (string) $key)->sort()->values()->all());

        return DB::connection('tenant')
            ->table('roles')
            ->orderByDesc('is_system')
            ->orderBy('key')
            ->get(['id', 'key', 'name', 'is_system'])
            ->mapWithKeys(fn (object $role): array => [
                (string) $role->key => $this->roleItem($role, collect($permissionsByRole->get((string) $role->key, []))),
            ]);
    }

    /**
     * @param  Collection<int, string>  $permissions
     * @return array{id: string, key: string, label: string, is_system: bool, permissions: list<string>}
     */
    private function roleItem(object $role, Collection $permissions): array
    {
        return [
            'id' => (string) $role->id,
            'key' => (string) $role->key,
            'label' => $this->localizedJson($role->name),
            'is_system' => (bool) $role->is_system,
            'permissions' => $permissions->sort()->values()->all(),
        ];
    }

    /** @return list<string> */
    private function permissionKeys(): array
    {
        return DB::connection('tenant')->table('permissions')->orderBy('key')->pluck('key')->map(fn (mixed $key): string => (string) $key)->all();
    }

    /** @return list<string> */
    private function validatedPermissionKeys(mixed $permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        $requested = collect($permissions)->map(fn (mixed $permission): string => (string) $permission)->unique()->values()->all();
        $valid = $this->permissionKeys();
        $unknown = array_values(array_diff($requested, $valid));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => ['Unknown permissions: '.implode(', ', $unknown)]]);
        }

        return $requested;
    }

    /** @param list<string> $permissions */
    private function syncRolePermissions(int $roleId, array $permissions): void
    {
        $permissionIds = DB::connection('tenant')
            ->table('permissions')
            ->whereIn('key', $permissions)
            ->pluck('id', 'key');

        DB::connection('tenant')->transaction(function () use ($roleId, $permissions, $permissionIds): void {
            DB::connection('tenant')->table('permission_role')->where('role_id', $roleId)->delete();

            foreach ($permissions as $permission) {
                DB::connection('tenant')->table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds->get($permission),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::connection('tenant')->table('roles')->where('id', $roleId)->update(['updated_at' => now()]);
        });
    }

    /** @return array{key: string, label: string, id: int} */
    private function dashboardRole(string $roleKey): array
    {
        if (! in_array($roleKey, ApplicationAccessMatrix::rolesFor('dashboard'), true)) {
            throw ValidationException::withMessages(['role_key' => ['Role is not allowed to access the dashboard.']]);
        }

        $role = DB::connection('tenant')->table('roles')->where('key', $roleKey)->first(['id', 'key', 'name']);
        if ($role === null) {
            throw ValidationException::withMessages(['role_key' => ['Unknown role key.']]);
        }

        return [
            'id' => (int) $role->id,
            'key' => (string) $role->key,
            'label' => $this->localizedJson($role->name),
        ];
    }

    private function ensureAnotherActiveSchoolAdmin(int $excludingCentralUserId): void
    {
        $exists = DB::connection('central')
            ->table('school_user')
            ->where('school_id', $this->tenantContext->schoolId())
            ->where('role_key', 'school_admin')
            ->where('status', 'active')
            ->where('user_id', '!=', $excludingCentralUserId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'account' => ['At least one other active school administrator is required before changing this account.'],
            ]);
        }
    }

    private function ensureSchoolMembership(int $centralUserId): void
    {
        $exists = DB::connection('central')
            ->table('school_user')
            ->where('school_id', $this->tenantContext->schoolId())
            ->where('user_id', $centralUserId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['account' => ['Admin account does not belong to this school.']]);
        }
    }

    private function adminAccount(int $centralUserId): array
    {
        $row = DB::connection('central')
            ->table('school_user')
            ->join('users', 'users.id', '=', 'school_user.user_id')
            ->where('school_user.school_id', $this->tenantContext->schoolId())
            ->where('school_user.user_id', $centralUserId)
            ->first(['users.id', 'users.name', 'users.email', 'school_user.role_key', 'school_user.status']);

        if ($row === null) {
            throw ValidationException::withMessages(['account' => ['Admin account does not belong to this school.']]);
        }

        return $this->adminAccountItems(collect([$row]))[0];
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function adminAccountItems(Collection $rows): array
    {
        $userIds = $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $roles = $this->rolesWithPermissions();
        $lastLogins = DB::connection('central')
            ->table('personal_access_tokens')
            ->where('school_id', $this->tenantContext->schoolId())
            ->where('app_type', 'dashboard')
            ->whereIn('tokenable_id', $userIds)
            ->selectRaw('tokenable_id, max(coalesce(last_used_at, created_at)) as last_login_at')
            ->groupBy('tokenable_id')
            ->pluck('last_login_at', 'tokenable_id');

        return $rows
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'phone' => null,
                'role_key' => (string) $row->role_key,
                'role_label' => $roles->get((string) $row->role_key)['label'] ?? (string) $row->role_key,
                'status' => (string) $row->status,
                'last_login_at' => $lastLogins->has((int) $row->id) ? Carbon::parse((string) $lastLogins->get((int) $row->id))->toISOString() : null,
            ])
            ->values()
            ->all();
    }

    private function localizedJson(mixed $json): string
    {
        if (! is_string($json) || $json === '') {
            return '';
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $value = $decoded['en'] ?? $decoded['ar'] ?? reset($decoded);

            return is_string($value) ? $value : '';
        }

        return $json;
    }
}
