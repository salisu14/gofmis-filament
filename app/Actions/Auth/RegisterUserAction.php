<?php

namespace App\Actions\Auth;

use App\Data\Auth\RegisterUserData;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * @throws \Throwable
     */
    public static function run(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data) {

            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
            ]);

            event(new Registered($user));

            return $user;
        });
    }
}
