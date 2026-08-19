<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            // First check account status (EnsureActiveUser is authoritative, but we protect defensively)
            if (! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->to('/admin/login')
                    ->withErrors(['email' => 'Your account has been deactivated, suspended, or locked.']);
            }

            $currentRoute = $request->route() ? $request->route()->getName() : '';

            // Allow pre-MFA pages, logout, and livewire requests serving those pages
            $allowedRoutes = [
                'mfa.challenge',
                'mfa.enroll',
                'mfa.recovery',
                'filament.admin.auth.logout',
                'filament.coordinator.auth.logout',
                'filament.imprest.auth.logout',
            ];

            if (in_array($currentRoute, $allowedRoutes, true)) {
                return $next($request);
            }

            // Exclude direct livewire request handlers if they originate from MFA pages
            if ($request->is('livewire/*') || $request->is('livewire/message/*')) {
                $referer = $request->header('Referer', '');
                if (str_contains($referer, '/mfa/')) {
                    return $next($request);
                }
            }

            // Evaluate MFA requirements
            if ($user->isMfaRequired() || $user->twoFactorAuthEnabled()) {
                // If MFA is required but they have no active/confirmed secret, force enrollment
                if ($user->isMfaRequired() && ! $user->twoFactorAuthEnabled()) {
                    session()->forget(['mfa_verified_at', 'mfa_verified_user_id']);
                    if ($request->header('X-Livewire')) {
                        return response('', 409, ['X-Livewire-Redirect' => route('mfa.enroll')]);
                    }

                    return redirect()->route('mfa.enroll');
                }

                $verifiedAt = session()->get('mfa_verified_at');
                $verifiedUserId = session()->get('mfa_verified_user_id');

                // Check if challenge session has expired (lifetime default: 2 hours)
                $sessionLifetime = config('session.lifetime', 120) * 60; // in seconds
                $mfaLifetime = min($sessionLifetime, 7200); // max 2 hours

                $isExpired = empty($verifiedAt) || (time() - $verifiedAt > $mfaLifetime);

                if ($verifiedUserId !== $user->id || $isExpired) {
                    // Session not verified or expired. Invalidate verification.
                    session()->forget(['mfa_verified_at', 'mfa_verified_user_id']);

                    // Redirect based on MFA enrollment status
                    if ($user->twoFactorAuthEnabled()) {
                        if ($request->header('X-Livewire')) {
                            return response('', 409, ['X-Livewire-Redirect' => route('mfa.challenge')]);
                        }

                        return redirect()->route('mfa.challenge');
                    } else {
                        if ($request->header('X-Livewire')) {
                            return response('', 409, ['X-Livewire-Redirect' => route('mfa.enroll')]);
                        }

                        return redirect()->route('mfa.enroll');
                    }
                }
            }
        }

        return $next($request);
    }
}
