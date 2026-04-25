<?php

namespace App\Actions\Role;

class UserRoleAction
{
    public static function assign(UserRoleData $data): User
    {
        $user = User::findOrFail($data->user_id);
        $role = Role::where('name', $data->role_name)->first();

        if (!$role) {
            throw ValidationException::withMessages([
                'role_name' => "Role '{$data->role_name}' does not exist.",
            ]);
        }

        $user->assignRole($data->role_name);
        return $user->load('roles');
    }

    public static function revoke(UserRoleData $data): User
    {
        $user = User::findOrFail($data->user_id);
        $role = Role::where('name', $data->role_name)->first();

        if (!$role) {
            throw ValidationException::withMessages([
                'role_name' => "Role '{$data->role_name}' does not exist.",
            ]);
        }

        $user->removeRole($data->role_name);
        return $user->load('roles');
    }

    public static function syncRoles(int $userId, array $roles): User
    {
        $user = User::findOrFail($userId);

        foreach ($roles as $roleName) {
            if (!Role::where('name', $roleName)->exists()) {
                throw ValidationException::withMessages([
                    'roles' => "Role '{$roleName}' does not exist.",
                ]);
            }
        }

        $user->syncRoles($roles);
        return $user->load('roles');
    }

}
