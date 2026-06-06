<?php

namespace App\Actions\Role;

use App\Models\Role;
use App\Models\User;

class AssignRoleToUserAction
{
    public function execute(string $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);

        Role::findByName($roleName, 'web');

        $user->assignRole($roleName);

        return $user->load('roles');
    }
}
