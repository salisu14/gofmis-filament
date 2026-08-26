<?php

namespace App\Livewire\Mfa;

use App\Services\MfaService;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MfaChallenge extends Component
{
    public string $code = '';

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

        // If they don't even have MFA enabled/required, let them pass
        $defaultUrl = match (true) {
            $user->hasRole('coordinator') => '/coordinator',
            $user->hasAnyRole(['custodian', 'auditor']) => '/imprest',
            default => '/admin',
        };

        if (! $user->isMfaRequired() && ! $user->twoFactorAuthEnabled()) {
            return redirect()->intended($defaultUrl);
        }

        // If already verified, redirect to dashboard or intended page
        if ($user->isMfaVerifiedInSession()) {
            return redirect()->intended($defaultUrl);
        }

        // If not enrolled yet but required, redirect to enrollment
        if ($user->isMfaRequired() && ! $user->twoFactorAuthEnabled()) {
            return redirect()->route('mfa.enroll');
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
            'code' => 'required|string|size:6',
        ]);

        $limiterKey = 'mfa-challenge:'.$user->id;
        $maxAttempts = config('security.mfa.rate_limits.challenge.max_attempts', 5);
        $decaySeconds = config('security.mfa.rate_limits.challenge.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            $this->addError('code', "Too many verification attempts. Please try again in {$seconds} seconds.");

            return;
        }

        $service = new MfaService;

        if ($service->verifyOtp($user, $this->code)) {
            RateLimiter::clear($limiterKey);

            session()->put('mfa_verified_at', time());
            session()->put('mfa_verified_user_id', $user->id);
            session()->regenerate();

            SecurityAuditService::log(
                'MFA_CHALLENGE_SUCCEEDED',
                "MFA challenge succeeded for user {$user->email}",
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
            "MFA challenge failed for user {$user->email}",
            $user,
            $user,
            ['reason' => 'Invalid OTP code']
        );

        $this->addError('code', 'The provided verification code is invalid.');
    }

    public function render()
    {
        return view('livewire.mfa.mfa-challenge')
            ->layout('layouts.mfa', ['title' => 'MFA Challenge - Garko Orphans Foundation']);
    }
}
