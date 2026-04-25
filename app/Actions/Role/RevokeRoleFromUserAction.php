<?php

namespace App\Actions\Role;

use App\Data\Role\UserRoleData;
use App\Models\User;

class RevokeRoleFromUserAction
{

    public function execute(int $userId, string $roleName): User
    {
        $data = new UserRoleData(user_id: $userId, role_name: $roleName);
        return UserRoleAction::revoke($data);
    }

}
