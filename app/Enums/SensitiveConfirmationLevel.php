<?php

namespace App\Enums;

enum SensitiveConfirmationLevel: string
{
    case NONE = 'none';
    case PASSWORD = 'password';
    case TYPED_PHRASE = 'typed_phrase';
    case PASSWORD_AND_PHRASE = 'password_and_phrase';
}
