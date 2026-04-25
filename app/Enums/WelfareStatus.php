<?php

namespace App\Enums;

enum WelfareStatus: string
{
    case OPENED = 'OPENED';
    case CLOSED = 'CLOSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPENED => 'Opened',
            self::CLOSED => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OPENED => 'success', // Green
            self::CLOSED => 'gray',    // Gray
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPENED => 'heroicon-o-folder-open',
            self::CLOSED => 'heroicon-o-lock-closed',
        };
    }
}
