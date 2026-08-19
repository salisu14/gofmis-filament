<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class MfaService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a new TOTP secret.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(16);
    }

    /**
     * Generate provisioning URI for QR code.
     */
    public function getProvisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            'Garko Orphans Foundation',
            $user->email,
            $secret
        );
    }

    /**
     * Verify a given OTP against a secret or user's stored secret.
     */
    public function verifyOtp(User $user, string $otp, ?string $secret = null): bool
    {
        $secret = $secret ?? $user->getAppAuthenticationSecret();

        if (empty($secret)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $otp, 8); // Window of 8
    }

    /**
     * Generate recovery codes, store their hashes, and return plaintext codes.
     */
    public function generateRecoveryCodes(User $user): array
    {
        $plainCodes = Collection::times(10, fn (): string => Str::random(10).'-'.Str::random(10)
        )->all();

        $hashedCodes = array_map(fn (string $code): string => Hash::make($code), $plainCodes);

        $user->saveAppAuthenticationRecoveryCodes($hashedCodes);

        SecurityAuditService::log(
            'MFA_RECOVERY_CODES_REGENERATED',
            "MFA recovery codes regenerated for user {$user->email}",
            auth()->user() ?? $user,
            $user
        );

        return $plainCodes;
    }

    /**
     * Verify and consume a recovery code atomically.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $lockKey = 'mfa.recovery_lock.'.$user->id;

        return Cache::lock($lockKey, 10)->block(10, function () use ($user, $code): bool {
            return DB::transaction(function () use ($user, $code): bool {
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

                if (! $lockedUser) {
                    return false;
                }

                $hashedCodes = $lockedUser->getAppAuthenticationRecoveryCodes() ?? [];
                $validIndex = null;

                foreach ($hashedCodes as $index => $hashedCode) {
                    if (Hash::check($code, $hashedCode)) {
                        $validIndex = $index;
                        break;
                    }
                }

                if ($validIndex !== null) {
                    unset($hashedCodes[$validIndex]);
                    $lockedUser->saveAppAuthenticationRecoveryCodes(array_values($hashedCodes));

                    SecurityAuditService::log(
                        'MFA_RECOVERY_CODE_USED',
                        "MFA recovery code used and consumed for user {$lockedUser->email}",
                        $lockedUser,
                        $lockedUser
                    );

                    return true;
                }

                return false;
            });
        });
    }

    /**
     * Confirm MFA enrollment using a first OTP.
     */
    public function confirmEnrollment(User $user, string $secret, string $otp): bool
    {
        if ($this->verifyOtp($user, $otp, $secret)) {
            DB::transaction(function () use ($user, $secret) {
                $user->saveAppAuthenticationSecret($secret);
                $user->update([
                    'mfa_confirmed_at' => now(),
                    'mfa_enabled_at' => now(),
                    'mfa_enrollment_required' => false,
                ]);
            });

            SecurityAuditService::log(
                'MFA_ENABLED',
                "MFA enabled and confirmed for user {$user->email}",
                $user,
                $user
            );

            return true;
        }

        return false;
    }

    /**
     * Disable MFA self-service (only allowed for optional roles).
     */
    public function disableMfa(User $user): void
    {
        if ($user->isMfaRequired()) {
            throw ValidationException::withMessages([
                'mfa' => ['Unauthorized: Cannot disable MFA for roles that require mandatory MFA.'],
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->saveAppAuthenticationSecret(null);
            $user->saveAppAuthenticationRecoveryCodes(null);
            $user->update([
                'mfa_confirmed_at' => null,
                'mfa_enabled_at' => null,
            ]);

            // Clear session verification state
            session()->forget(['mfa_verified_at', 'mfa_verified_user_id']);
        });

        SecurityAuditService::log(
            'MFA_DISABLED',
            "MFA disabled for user {$user->email}",
            $user,
            $user
        );
    }

    /**
     * Reconfigure MFA. Keeping old secret active until replacement is successfully confirmed.
     */
    public function confirmReconfiguration(User $user, string $newSecret, string $otp): bool
    {
        if ($this->verifyOtp($user, $otp, $newSecret)) {
            DB::transaction(function () use ($user, $newSecret) {
                $user->saveAppAuthenticationSecret($newSecret);
                $user->update([
                    'mfa_confirmed_at' => now(),
                    'mfa_enabled_at' => now(),
                ]);
            });

            SecurityAuditService::log(
                'MFA_RECONFIGURED',
                "MFA reconfigured and confirmed for user {$user->email}",
                $user,
                $user
            );

            return true;
        }

        return false;
    }

    /**
     * Administrative MFA Reset.
     */
    public function resetMfa(User $actor, User $target): void
    {
        if (Gate::forUser($actor)->denies('resetMfa', $target)) {
            throw ValidationException::withMessages([
                'mfa' => ['Unauthorized: You are not authorized to reset MFA for this user.'],
            ]);
        }

        DB::transaction(function () use ($target) {
            $target->saveAppAuthenticationSecret(null);
            $target->saveAppAuthenticationRecoveryCodes(null);
            $target->update([
                'mfa_confirmed_at' => null,
                'mfa_enabled_at' => null,
                'mfa_enrollment_required' => $target->isMfaRequired(),
            ]);

            // Clear session verification state if this target is the authenticated user or force next session verification
            if ($target->id === auth()->id()) {
                session()->forget(['mfa_verified_at', 'mfa_verified_user_id']);
            }
        });

        // Invalidate active target sessions if session database driver or similar is configured.
        // For standard session store, clearing the verification session keys on next request via middleware will catch it.

        SecurityAuditService::log(
            'MFA_RESET_BY_ADMIN',
            "MFA reset for user {$target->email} by administrator {$actor->email}",
            $actor,
            $target,
            ['actor_id' => $actor->id, 'target_user_id' => $target->id]
        );
    }

    /**
     * Force target user to enroll in MFA on next login.
     */
    public function requireMfaEnrollment(User $actor, User $target): void
    {
        if (Gate::forUser($actor)->denies('update', $target)) {
            throw ValidationException::withMessages([
                'mfa' => ['Unauthorized: You are not authorized to force MFA enrollment for this user.'],
            ]);
        }

        DB::transaction(function () use ($actor, $target) {
            $target->update(['mfa_enrollment_required' => true]);

            SecurityAuditService::log(
                'MFA_ENROLLMENT_FORCED',
                "MFA enrollment was administratively forced for {$target->email} by admin {$actor->email}",
                $actor,
                $target,
                ['actor_id' => $actor->id, 'target_user_id' => $target->id]
            );
        });
    }
}
