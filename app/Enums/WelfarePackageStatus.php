<?php

namespace App\Enums;

enum WelfarePackageStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT   => 'Draft',
            self::OPEN    => 'Open',
            self::CLOSED  => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT   => 'gray',
            self::OPEN    => 'success',
            self::CLOSED  => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT   => 'heroicon-o-pencil-square',
            self::OPEN    => 'heroicon-o-check-circle',
            self::CLOSED  => 'heroicon-o-lock-closed',
        };
    }

    /**
     * Structural state-machine transition rules only.
     *
     * Allowed transitions:
     *   DRAFT  → OPEN    (open)
     *   OPEN   → CLOSED  (close)
     *   CLOSED → OPEN    (reopen)
     *
     * All other combinations are denied.
     * This method contains NO side-effects and does NOT query the database.
     * Domain guards (item existence, nomination counts, collection status)
     * are the responsibility of WelfarePackageLifecycleService.
     */
    public function canTransitionTo(self $target): bool
    {
        return match (true) {
            $this === self::DRAFT  && $target === self::OPEN   => true,
            $this === self::OPEN   && $target === self::CLOSED => true,
            $this === self::CLOSED && $target === self::OPEN   => true,
            default => false,
        };
    }
}
