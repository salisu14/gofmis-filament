<?php

namespace App\Services\Security;

use App\Exceptions\DemoReadOnlyException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DemoReadOnlyGuard
{
    /**
     * Determine if the given or currently authenticated user is a Demo Observer.
     */
    public static function isDemoUser(?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        return $user?->isDemoObserver() ?? false;
    }

    /**
     * Enforce system-wide read-only mutation denial.
     * Throws DemoReadOnlyException if the actor is a Demo Observer.
     *
     * @throws DemoReadOnlyException
     */
    public static function ensureCanMutate(?User $user = null): void
    {
        if (self::isDemoUser($user)) {
            throw new DemoReadOnlyException('Demo Mode — Read Only. Persistent system modifications are strictly prohibited.');
        }
    }

    /**
     * Enforce system-wide data export and sensitive document download denial.
     * Throws DemoReadOnlyException if the actor is a Demo Observer.
     *
     * @throws DemoReadOnlyException
     */
    public static function ensureCanExportSensitiveData(?User $user = null): void
    {
        if (self::isDemoUser($user)) {
            throw new DemoReadOnlyException('Demo Mode — Data export and downloading are disabled for this demonstration account.');
        }
    }
}
