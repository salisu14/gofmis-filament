<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account has been deactivated, suspended, or locked.'], 403);
            }

            $loginUrl = match (true) {
                $request->is('coordinator*') || $user->hasRole('coordinator') => route('filament.coordinator.auth.login'),
                $request->is('imprest*') || $user->hasAnyRole(['custodian', 'auditor']) => route('filament.imprest.auth.login'),
                default => route('filament.admin.auth.login'),
            };

            return redirect()->to($loginUrl)
                ->withErrors(['email' => 'Your account has been deactivated, suspended, or locked.']);
        }

        return $next($request);
    }
}
