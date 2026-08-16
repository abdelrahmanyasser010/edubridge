<?php

namespace Database\Seeders\Tenant;

use App\Auth\PermissionCatalog;
use App\Auth\SystemRoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantRbacSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $connection = DB::connection('tenant');

        foreach (PermissionCatalog::keys() as $permission) {
            $connection->table('permissions')->updateOrInsert(
                ['key' => $permission],
                [
                    'description' => json_encode(['en' => $permission]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach (SystemRoleCatalog::permissionsByRole() as $role => $permissions) {
            $connection->table('roles')->updateOrInsert(
                ['key' => $role],
                [
                    'name' => json_encode(['en' => str_replace('_', ' ', $role)]),
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $roleId = $connection->table('roles')->where('key', $role)->value('id');

            foreach ($permissions as $permission) {
                $permissionId = $connection->table('permissions')->where('key', $permission)->value('id');

                $connection->table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
}
