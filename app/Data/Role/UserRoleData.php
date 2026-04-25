<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class UserRoleData extends Data
{
    public int $user_id;
    public string $user_email;
    public int $role_id;
    public string $role_name;


    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'role_name' => ['required', 'string', 'exists:roles,name'],
            'user_email' => ['required', 'email', 'exists:users,email'],
        ];
    }

}
