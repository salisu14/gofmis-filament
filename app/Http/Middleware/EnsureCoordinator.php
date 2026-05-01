<?php

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

        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);
        $isCoordinator = $user->hasRole('coordinator');

        // Allow only coordinator/admin
        if (! $isAdmin && ! $isCoordinator) {
            abort(403, 'Access denied. Coordinators only.');
        }

        // Load zone once (important for performance)
        $user->loadMissing('coordinatedZone');

        // For coordinators (not admins), require zone assignment
        if ($isCoordinator && ! $user->coordinatedZone) {
            abort(403, 'No zone assigned. Contact administrator.');
        }

        // Bind zone safely
        $request->attributes->set('coordinator_zone_id', $user->zoneId());
//        $request->attributes->set(
//            'coordinator_zone_id',
//            $user->coordinatedZone?->id // ✅ correct
//        );


        $request->attributes->set(
            'user_is_admin',
            $isAdmin // ✅ fixed
        );

        return $next($request);
    }
}
