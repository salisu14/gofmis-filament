<?php

namespace App\Livewire\Mfa;

use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class MfaSettings extends Component
{
    public string $password = '';

    public string $phrase = '';

    // Actions visibility
    public string $action = ''; // 'disable', 'regenerate', 'reconfigure'

    // Reconfigure specific state
    public string $newSecret = '';

    public string $provisioningUri = '';

    public string $otp = '';

    public array $newRecoveryCodes = [];

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

        // If user is not verified but requires MFA or has it enabled, redirect to challenge
        if (($user->isMfaRequired() || $user->twoFactorAuthEnabled()) && ! $user->isMfaVerifiedInSession()) {
            if ($user->twoFactorAuthEnabled()) {
                return redirect()->route('mfa.challenge');
            } else {
                return redirect()->route('mfa.enroll');
            }
        }
    }

    public function selectAction(string $actionName)
    {
        $user = Auth::user();
        if ($actionName === 'disable' && $user->isMfaRequired()) {
            $this->addError('mfa', 'Unauthorized: Cannot disable MFA for roles that require mandatory MFA.');

            return;
        }

        $this->action = $actionName;
        $this->password = '';
        $this->phrase = '';
        $this->newRecoveryCodes = [];
        $this->otp = '';

        if ($actionName === 'reconfigure') {
            $service = new MfaService;
            $this->newSecret = $service->generateSecret();
            $this->provisioningUri = $service->getProvisioningUri($user, $this->newSecret);
            session()->put('mfa_reconfigure_pending_secret', $this->newSecret);
        }
    }

    public function cancelAction()
    {
        $this->action = '';
        $this->password = '';
        $this->phrase = '';
        $this->newRecoveryCodes = [];
        $this->otp = '';
        session()->forget('mfa_reconfigure_pending_secret');
    }

    public function disableMfa()
    {
        $user = Auth::user();
        if ($user->isMfaRequired()) {
            $this->addError('password', 'Unauthorized: Cannot disable MFA for roles that require mandatory MFA.');

            return;
        }

        $this->validate([
            'password' => 'required',
            'phrase' => 'required|string',
        ]);

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The provided password is incorrect.');

            return;
        }

        if (trim($this->phrase) !== 'DISABLE MFA') {
            $this->addError('phrase', 'Please enter the exact phrase: DISABLE MFA');

            return;
        }

        $service = new MfaService;
        $service->disableMfa($user);

        $this->cancelAction();
        session()->flash('status', 'Two-Factor Authentication has been successfully disabled.');

        return redirect()->route('mfa.settings');
    }

    public function regenerateRecoveryCodes()
    {
        $user = Auth::user();
        $this->validate([
            'password' => 'required',
        ]);

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The provided password is incorrect.');

            return;
        }

        $service = new MfaService;
        $this->newRecoveryCodes = $service->generateRecoveryCodes($user);
        $this->password = '';
        // Stay in regenerate state to display the codes
    }

    public function downloadRecoveryCodes(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if (empty($this->newRecoveryCodes)) {
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

        foreach ($this->newRecoveryCodes as $index => $code) {
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

    public function verifyReconfigure()
    {
        $user = Auth::user();
        $this->validate([
            'otp' => 'required|string|size:6',
        ]);

        $pendingSecret = session()->get('mfa_reconfigure_pending_secret');
        if (! $pendingSecret) {
            $this->addError('otp', 'Invalid session setup key. Please restart.');

            return;
        }

        $service = new MfaService;
        if ($service->confirmReconfiguration($user, $pendingSecret, $this->otp)) {
            session()->forget('mfa_reconfigure_pending_secret');

            // Re-verify the current session
            session()->put('mfa_verified_at', time());
            session()->put('mfa_verified_user_id', $user->id);

            $this->cancelAction();
            session()->flash('status', 'Authenticator has been successfully reconfigured.');

            return redirect()->route('mfa.settings');
        }

        $this->addError('otp', 'The provided verification code is invalid.');
    }

    public function render()
    {
        return view('livewire.mfa.mfa-settings')
            ->layout('layouts.mfa', ['title' => 'Security Settings - Garko Orphans Foundation']);
    }
}
