<?php

namespace App\Actions\Rbac;

use App\Auth\SystemRoleCatalog;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TenantUserRoleSynchronizer
{
    public function __construct(
        private readonly TenantConnectionResolver $resolver,
        private readonly TenantConnectionManager $manager,
        private readonly TenantContext $tenantContext,
    ) {}

    public function syncUser(int $schoolId, int $centralUserId): void
    {
        $membership = DB::connection('central')
            ->table('school_user')
            ->where('school_id', $schoolId)
            ->where('user_id', $centralUserId)
            ->first(['role_key', 'status', 'valid_from', 'valid_until']);

        if ($this->tenantContext->active() && $this->tenantContext->schoolId() === $schoolId) {
            $this->executeSync($centralUserId, $membership);

            return;
        }

        $tenant = $this->resolver->resolveBySchoolId($schoolId);
        $this->manager->run($tenant, function () use ($centralUserId, $membership): void {
            $this->executeSync($centralUserId, $membership);
        });
    }

    public function syncAllForSchool(int $schoolId): int
    {
        $memberships = DB::connection('central')
            ->table('school_user')
            ->where('school_id', $schoolId)
            ->get(['user_id', 'role_key', 'status', 'valid_from', 'valid_until']);

        $runner = function () use ($memberships): int {
            $count = 0;
            foreach ($memberships as $membership) {
                $this->executeSync((int) $membership->user_id, $membership);
                $count++;
            }

            return $count;
        };

        if ($this->tenantContext->active() && $this->tenantContext->schoolId() === $schoolId) {
            return $runner();
        }

        $tenant = $this->resolver->resolveBySchoolId($schoolId);

        return $this->manager->run($tenant, $runner);
    }

    private function executeSync(int $centralUserId, ?object $membership): void
    {
        $systemRoleKeys = array_keys(SystemRoleCatalog::permissionsByRole());
        /** @var Collection<string, int> $systemRoleIds */
        $systemRoleIds = DB::connection('tenant')
            ->table('roles')
            ->whereIn('key', $systemRoleKeys)
            ->pluck('id', 'key');

        if ($membership === null || (string) $membership->status !== 'active') {
            DB::connection('tenant')
                ->table('user_roles')
                ->where('central_user_id', $centralUserId)
                ->whereIn('role_id', $systemRoleIds->values()->all())
                ->delete();

            return;
        }

        $roleKey = (string) $membership->role_key;
        $targetRoleId = $systemRoleIds->get($roleKey);

        if ($targetRoleId === null) {
            $targetRoleId = DB::connection('tenant')
                ->table('roles')
                ->where('key', $roleKey)
                ->value('id');
        }

        if ($targetRoleId === null) {
            DB::connection('tenant')
                ->table('user_roles')
                ->where('central_user_id', $centralUserId)
                ->whereIn('role_id', $systemRoleIds->values()->all())
                ->delete();

            return;
        }

        $now = now();
        $targetRoleIdInt = (int) $targetRoleId;

        DB::connection('tenant')->transaction(function () use ($centralUserId, $systemRoleIds, $targetRoleIdInt, $membership, $now): void {
            DB::connection('tenant')
                ->table('user_roles')
                ->where('central_user_id', $centralUserId)
                ->whereIn('role_id', $systemRoleIds->values()->all())
                ->where('role_id', '!=', $targetRoleIdInt)
                ->delete();

            DB::connection('tenant')->table('user_roles')->updateOrInsert(
                [
                    'central_user_id' => $centralUserId,
                    'role_id' => $targetRoleIdInt,
                ],
                [
                    'valid_from' => $membership->valid_from,
                    'valid_until' => $membership->valid_until,
                    'updated_at' => $now,
                ],
            );
        });
    }
}
