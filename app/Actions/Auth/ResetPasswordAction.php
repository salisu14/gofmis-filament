<?php

namespace App\Actions\Auth;

use App\Data\Auth\ResetPasswordData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordAction
{
    public static function run(ResetPasswordData $data): bool
    {
        $status = Password::reset(
            [
                'email' => $data->email,
                'password' => $data->password,
                'password_confirmation' => $data->password_confirmation,
                'token' => $data->token,
            ],
            function (User $user) use ($data) {

                $user->forceFill([
                    'password' => Hash::make($data->password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}
