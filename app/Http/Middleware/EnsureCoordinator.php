<?php
// app/Http/Middleware/EnsureCoordinator.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoordinator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Use Spatie permission check
        if (!$user->hasAnyRole(['coordinator', 'admin', 'super_admin'])) {
            abort(403, 'Access denied. Coordinators only.');
        }

        // For coordinators (not admins), require zone assignment
        if ($user->hasAnyRole('coordinator') && !$user->zone_id) {
            abort(403, 'No zone assigned. Contact administrator.');
        }

        // Bind coordinator's zone to request
        $request->attributes->set('coordinator_zone_id', $user->zone_id);
        $request->attributes->set('user_is_admin', $user->hasRole(['admin', 'super_admin']));

        return $next($request);
    }
}
