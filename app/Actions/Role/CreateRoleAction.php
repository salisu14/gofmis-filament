<?php

namespace App\Actions\Role;

use App\Models\Role;

class CreateRoleAction
{
    public function execute(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
