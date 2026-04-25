<?php

namespace App\Actions\User;

use App\Data\User\UpdateUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserAction
{
    public static function run(User $user, UpdateUserData $data): User
    {
        $attributes = [
            'name' => $data->name,
            'email' => $data->email,
        ];

        if (! empty($data->password)) {
            $attributes['password'] = Hash::make($data->password);
        }

        $user->update($attributes);

        return $user;
    }
}
