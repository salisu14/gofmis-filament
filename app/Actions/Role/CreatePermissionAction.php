<?php

namespace App\Actions\Role;

use Spatie\Permission\Models\Permission;

class CreatePermissionAction
{

    public function execute(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name]);
    }

}
