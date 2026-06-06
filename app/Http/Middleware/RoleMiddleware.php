<?php

// app/Http/Middleware/RoleMiddleware.php
// FIXED: Only applies role checks to specific resources, not globally blocking all users

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Resources that require role-based access control.
     * Add resource slugs here that should be restricted.
     */
    protected array $restrictedResources = [
        'education-verification',
    ];

    /**
     * Allowed roles for restricted resources.
     */
    protected array $allowedRoles = [
        'admin',
        'super_admin',
        'education-verifier',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request); // Let auth middleware handle unauthenticated
        }

        // Check if current route is a restricted resource
        $currentPath = $request->path();
        $isRestricted = false;

        foreach ($this->restrictedResources as $resource) {
            if (str_contains($currentPath, $resource)) {
                $isRestricted = true;
                break;
            }
        }

        // If not a restricted resource, allow access (respect existing auth)
        if (! $isRestricted) {
            return $next($request);
        }

        // For restricted resources, check role
        if (method_exists($user, 'hasAnyRole')) {
            if (! $user->hasAnyRole($this->allowedRoles)) {
                abort(403, 'You do not have permission to access the education verification area.');
            }
        }

        return $next($request);
    }
}
