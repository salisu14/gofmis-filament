<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class CreateRoleData extends Data
{
    public string $name;
    public array $permissions;

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

}
