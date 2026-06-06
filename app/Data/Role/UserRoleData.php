<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class UserRoleData extends Data
{
    public string $user_id;

    public string $user_email;

    public string $role_id;

    public string $role_name;

    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_id' => ['required', 'uuid', 'exists:roles,uuid'],
            'role_name' => ['required', 'string', 'exists:roles,name'],
            'user_email' => ['required', 'email', 'exists:users,email'],
        ];
    }
}
