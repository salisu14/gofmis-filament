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

        // If not enrolled yet but required, redirect to enrollment
        if ($user->isMfaRequired() && ! $user->twoFactorAuthEnabled()) {
            return redirect()->route('mfa.enroll');
        }
    }

    public function verify()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/admin/login');
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
