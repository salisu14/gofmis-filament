<?php

namespace App\Actions\Role;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncUserRolesAction
{
    /**
     * @throws \Throwable
     */
    public function execute(User $user, array $roles): User
    {
        DB::transaction(function () use ($user, $roles) {
            $user->syncRoles($roles);
        });

        return $user->load('roles');
    }
}
