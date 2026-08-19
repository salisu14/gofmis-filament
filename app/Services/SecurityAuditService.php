<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Request;

class SecurityAuditService
{
    public static function log(
        string $event,
        ?string $description = null,
        ?User $actor = null,
        mixed $target = null,
        array $properties = []
    ): void {
        $actor = $actor ?: auth()->user();

        // Build safe audit properties
        $safeProperties = array_merge([
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ], $properties);

        // Remove any potentially sensitive fields
        unset(
            $safeProperties['password'],
            $safeProperties['password_confirmation'],
            $safeProperties['current_password'],
            $safeProperties['secret'],
            $safeProperties['recovery_codes'],
            $safeProperties['app_authentication_secret'],
            $safeProperties['app_authentication_recovery_codes']
        );

        $activity = activity('security')
            ->event($event)
            ->withProperties($safeProperties);

        if ($actor) {
            $activity->causedBy($actor);
        }

        if ($target && is_object($target) && method_exists($target, 'getKey')) {
            $activity->performedOn($target);
        }

        $activity->log($description ?: "Security event: {$event}");
    }
}
