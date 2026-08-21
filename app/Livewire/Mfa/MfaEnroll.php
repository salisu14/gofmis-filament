<?php

namespace App\Livewire\Mfa;

use App\Services\MfaService;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MfaEnroll extends Component
{
    public int $step = 1;

    public string $password = '';

    public string $pendingSecret = '';

    public string $provisioningUri = '';

    public string $otp = '';

    public array $recoveryCodes = [];

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

        // If already fully enrolled, redirect to settings/dashboard
        if ($user->twoFactorAuthEnabled()) {
            return redirect()->to('/admin');
        }
    }

    public function verifyPassword()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/admin/login');
        }

        $this->validate([
            'password' => 'required',
        ]);

        if (Hash::check($this->password, $user->password)) {
            $this->password = '';

            // Step 2: Generate/rotate the pending secret and store it in session
            $service = new MfaService;
            $this->pendingSecret = $service->generateSecret();
            $this->provisioningUri = $service->getProvisioningUri($user, $this->pendingSecret);

            session()->put('mfa_pending_secret', $this->pendingSecret);

            SecurityAuditService::log(
                'MFA_ENROLLMENT_STARTED',
                "MFA enrollment started for user {$user->email}",
                $user,
                $user
            );

            $this->step = 2;

            return;
        }

        $this->addError('password', 'The provided password does not match our records.');
    }

    public function verifyOtp()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/admin/login');
        }

        $this->validate([
            'otp' => 'required|string|size:6',
        ]);

        $limiterKey = 'mfa-enrollment:'.$user->id;
        $maxAttempts = config('security.mfa.rate_limits.enrollment.max_attempts', 5);
        $decaySeconds = config('security.mfa.rate_limits.enrollment.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            $this->addError('otp', "Too many verification attempts. Please try again in {$seconds} seconds.");

            return;
        }

        $service = new MfaService;
        $storedPendingSecret = session()->get('mfa_pending_secret');

        if ($storedPendingSecret && $service->confirmEnrollment($user, $storedPendingSecret, $this->otp)) {
            RateLimiter::clear($limiterKey);
            session()->forget('mfa_pending_secret');

            // Generate recovery codes
            $this->recoveryCodes = $service->generateRecoveryCodes($user);

            $this->step = 3;

            return;
        }

        RateLimiter::hit($limiterKey, $decaySeconds);

        $this->addError('otp', 'The provided verification code is invalid.');
    }

    public function downloadRecoveryCodes(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if (empty($this->recoveryCodes)) {
            $this->addError('download', 'Recovery codes are no longer available. Generate a new set if needed.');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'download' => 'Recovery codes are no longer available. Generate a new set if needed.',
            ]);
        }

        $email = $user->email;
        $timestamp = now()->format('Y-m-d H:i:s');
        $date = now()->format('Y-m-d');

        $content = "GARKO ORPHANS FOUNDATION MIS\n";
        $content .= "MULTI-FACTOR AUTHENTICATION RECOVERY CODES\n\n";
        $content .= "Account: {$email}\n";
        $content .= "Generated: {$timestamp}\n\n";

        foreach ($this->recoveryCodes as $index => $code) {
            $content .= ($index + 1).". {$code}\n";
        }

        $content .= "\nIMPORTANT:\n";
        $content .= "- Each recovery code can be used once.\n";
        $content .= "- Store this file in a secure location.\n";
        $content .= "- Do not share these codes.\n";
        $content .= "- If you believe they have been exposed, regenerate your recovery codes.\n";

        return response()->streamDownload(
            fn () => print ($content),
            "gof-mis-recovery-codes-{$date}.txt",
            [
                'Content-Type' => 'text/plain',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function complete()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/admin/login');
        }

        // Clear transient recovery codes
        $this->recoveryCodes = [];

        // Set session verification state
        session()->put('mfa_verified_at', time());
        session()->put('mfa_verified_user_id', $user->id);

        return redirect()->route('mfa.settings');
    }

    public function render()
    {
        return view('livewire.mfa.mfa-enroll')
            ->layout('layouts.mfa', ['title' => 'MFA Enrollment - Garko Orphans Foundation']);
    }
}
