<?php

namespace App\Livewire\Mfa;

use App\Services\MfaService;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MfaRecovery extends Component
{
    public string $recoveryCode = '';

    public function mount()
    {
        $user = Auth::user();

        if (! $user) {
            $intended = session()->get('url.intended', '');
            $referer = request()->header('Referer', '');

            if (str_contains($intended, '/coordinator') || str_contains($referer, '/coordinator')) {
                return redirect()->route('filament.coordinator.auth.login');
            }
            if (str_contains($intended, '/imprest') || str_contains($referer, '/imprest')) {
                return redirect()->route('filament.imprest.auth.login');
            }

            return redirect()->route('filament.admin.auth.login');
        }

        $defaultUrl = match (true) {
            $user->hasRole('coordinator') => '/coordinator',
            $user->hasAnyRole(['custodian', 'auditor']) => '/imprest',
            default => '/admin',
        };

        // If they don't even have MFA enabled/required, let them pass
        if (! $user->isMfaRequired() && ! $user->twoFactorAuthEnabled()) {
            return redirect()->intended($defaultUrl);
        }

        // If already verified, redirect to dashboard or intended page
        if ($user->isMfaVerifiedInSession()) {
            return redirect()->intended($defaultUrl);
        }
    }

    public function verify()
    {
        $user = Auth::user();

        if (! $user) {
            $intended = session()->get('url.intended', '');
            $referer = request()->header('Referer', '');

            if (str_contains($intended, '/coordinator') || str_contains($referer, '/coordinator')) {
                return redirect()->route('filament.coordinator.auth.login');
            }
            if (str_contains($intended, '/imprest') || str_contains($referer, '/imprest')) {
                return redirect()->route('filament.imprest.auth.login');
            }

            return redirect()->route('filament.admin.auth.login');
        }

        $this->validate([
            'recoveryCode' => 'required|string',
        ]);

        $limiterKey = 'mfa-recovery:'.$user->id;
        $maxAttempts = config('security.mfa.rate_limits.recovery.max_attempts', 5);
        $decaySeconds = config('security.mfa.rate_limits.recovery.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            $this->addError('recoveryCode', "Too many verification attempts. Please try again in {$seconds} seconds.");

            return;
        }

        $service = new MfaService;

        if ($service->verifyRecoveryCode($user, $this->recoveryCode)) {
            RateLimiter::clear($limiterKey);

            session()->put('mfa_verified_at', time());
            session()->put('mfa_verified_user_id', $user->id);
            session()->regenerate();

            SecurityAuditService::log(
                'MFA_CHALLENGE_SUCCEEDED',
                "MFA challenge succeeded using recovery code for user {$user->email}",
                $user,
                $user
            );

            // Redirect based on panel / roles or intended destination
            $defaultUrl = match (true) {
                $user->hasRole('coordinator') => '/coordinator',
                $user->hasAnyRole(['custodian', 'auditor']) => '/imprest',
                default => '/admin',
            };

            return redirect()->intended($defaultUrl);
        }

        RateLimiter::hit($limiterKey, $decaySeconds);

        SecurityAuditService::log(
            'MFA_CHALLENGE_FAILED',
            "MFA recovery code login failed for user {$user->email}",
            $user,
            $user,
            ['reason' => 'Invalid recovery code']
        );

        $this->addError('recoveryCode', 'The provided recovery code is invalid or has already been used.');
    }

    public function render()
    {
        return view('livewire.mfa.mfa-recovery')
            ->layout('layouts.mfa', ['title' => 'MFA Recovery - Garko Orphans Foundation']);
    }
}
