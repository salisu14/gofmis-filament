<?php

namespace App\Actions\Role;

use Spatie\Permission\Models\Role;

class CreateRoleAction
{

    public function execute(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

}
