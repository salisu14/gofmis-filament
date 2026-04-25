<?php

namespace App\Actions\Role;

use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignRoleToUserAction
{

    public function execute(string $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);

        Role::findByName($roleName); // guard-safe validation

        $user->assignRole($roleName);

        return $user->load('roles');
    }
}
