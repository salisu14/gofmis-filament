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
            return redirect()->to('/admin/login');
        }

        // If they don't even have MFA enabled/required, let them pass
        if (! $user->isMfaRequired() && ! $user->twoFactorAuthEnabled()) {
            return redirect()->to('/admin');
        }

        // If already verified, redirect to dashboard
        if ($user->isMfaVerifiedInSession()) {
            return redirect()->to('/admin');
        }
    }

    public function verify()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/admin/login');
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

            // Redirect based on panel / roles
            if ($user->hasRole('coordinator')) {
                return redirect()->to('/coordinator');
            }
            if ($user->hasAnyRole(['custodian', 'auditor'])) {
                return redirect()->to('/imprest');
            }

            return redirect()->to('/admin');
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
