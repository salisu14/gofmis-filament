<?php

namespace App\Data\Auth;

use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Data;

class ResetPasswordData extends Data
{
    public string $name;

    public string $email;

    public ?string $password;

    public ?string $password_confirmation;

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
