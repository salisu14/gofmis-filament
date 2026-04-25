<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class PermissionData extends Data
{

    public string $name;

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ];
    }

}