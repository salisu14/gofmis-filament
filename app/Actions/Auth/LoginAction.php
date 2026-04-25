<?php

namespace App\Actions\Auth;

use App\Data\Auth\LoginData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public static function run(LoginData $data)
    {
        /** @var \App\Models\User $user */
        $user = User::where('email', $data->email)->first();

        // Check if the user exists and the password is correct
        if (!$user || !Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Invalid login credentials.'),
            ]);
        }

        // Issue auth token.
        $token = $user->createToken('auth_token')->plainTextToken;

        return $token;
    }
}
