<?php

namespace App\Actions\Role;

use Spatie\Permission\Models\Role;

class SyncRolePermissionsAction
{

    public function execute(string $roleName, array $permissions): void
    {
        $role = Role::findByName($roleName);
        $role->syncPermissions($permissions);
    }

}
