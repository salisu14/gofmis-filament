<?php

namespace App\Actions\Role;

use App\Models\Role;

class SyncRolePermissionsAction
{
    public function execute(string $roleName, array $permissions): void
    {
        $role = Role::findByName($roleName, 'web');
        $role->syncPermissions($permissions);
    }
}
