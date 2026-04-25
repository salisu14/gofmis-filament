<?php

namespace App\Enums;

enum Gender: string
{
    case MALE = 'MALE';
    case FEMALE = 'FEMALE';

    public function getLabel(): string
    {
        return match ($this) {
            self::MALE   => 'Male',
            self::FEMALE => 'Female',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MALE   => 'info',    // Blue
            self::FEMALE => 'danger',  // Pink/Red tint
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MALE   => 'heroicon-o-user',
            self::FEMALE => 'heroicon-o-user-group',
        };
    }
}
