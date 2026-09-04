<?php

namespace App\Models;

use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar
{
    use HasFactory, HasUuids, Notifiable;
    use HasRoles {
        syncRoles as traitSyncRoles;
        assignRole as traitAssignRole;
        removeRole as traitRemoveRole;
    }
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;

    protected $keyType = 'string';

    public $incrementing = false;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'alternate_phone',
        'designation',
        'address',
        'photo',
        'date_of_birth',
        'gender',
        'is_active',
        'is_protected_system_account',
        'status',
        'disabled_at',
        'disabled_by',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
        'locked_at',
        'password_reset_required',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'mfa_enabled_at',
        'mfa_confirmed_at',
        'mfa_enrollment_required',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'is_protected_system_account' => 'boolean',
            'status' => \App\Enums\UserStatus::class,
            'disabled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'locked_at' => 'datetime',
            'password_reset_required' => 'boolean',
            'mfa_enabled_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'mfa_enrollment_required' => 'boolean',
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo;
    }

    /* ─────────────────────────────────────────
       Zone / Coordinator
       ───────────────────────────────────────── */
    public function coordinatedZone(): HasOne
    {
        return $this->hasOne(Zone::class, 'coordinator_id');
    }

    public function coordinatorHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ZoneCoordinatorHistory::class, 'user_id');
    }

    public function hasZone(): bool
    {
        return $this->coordinatedZone()->exists();
    }

    public function zoneId(): ?string
    {
        return $this->coordinatedZone?->id;
    }

    public function isCoordinator(): bool
    {
        return $this->hasRole('coordinator');
    }

    public function isAssignedCoordinator(): bool
    {
        return $this->isCoordinator() && $this->hasZone();
    }

    public function managesZone(?string $zoneId = null): bool
    {
        if (! $this->isCoordinator()) {
            return false;
        }
        $managedZoneId = $this->zoneId();
        if ($zoneId === null) {
            return $managedZoneId !== null;
        }

        return $managedZoneId === $zoneId;
    }

    public function restrictedZoneId(): ?string
    {
        return $this->isCoordinator() ? $this->zoneId() : null;
    }

    /* ─────────────────────────────────────────
       Role Helpers
       ───────────────────────────────────────── */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(['admin', 'super_admin']);
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function isDemoObserver(): bool
    {
        return $this->hasRole('demo_observer');
    }

    public function isProtectedSystemAccount(): bool
    {
        return (bool) ($this->is_protected_system_account ?? false) || $this->isDemoObserver();
    }

    public function hasElevatedPrivileges(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public static function getActiveSuperAdminCount(): int
    {
        return \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.uuid')
            ->join('users', 'model_has_roles.model_uuid', '=', 'users.id')
            ->where('roles.name', 'super_admin')
            ->where('users.is_active', true)
            ->where('users.status', \App\Enums\UserStatus::ACTIVE->value)
            ->lockForUpdate()
            ->count();
    }

    /* ─────────────────────────────────────────
       Account Status Helpers
       ───────────────────────────────────────── */
    public function isActive(): bool
    {
        return $this->is_active && $this->status === \App\Enums\UserStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === \App\Enums\UserStatus::SUSPENDED;
    }

    public function isDisabled(): bool
    {
        return $this->status === \App\Enums\UserStatus::DISABLED;
    }

    public function isLocked(): bool
    {
        return $this->status === \App\Enums\UserStatus::LOCKED;
    }

    public function disable(\App\Models\User $by): void
    {
        if ($this->isProtectedSystemAccount()) {
            throw new \RuntimeException('Protected system accounts cannot be disabled.');
        }

        $this->update([
            'is_active' => false,
            'status' => \App\Enums\UserStatus::DISABLED,
            'disabled_at' => now(),
            'disabled_by' => $by->id,
        ]);
    }

    public function suspend(\App\Models\User $by, string $reason): void
    {
        if ($this->isProtectedSystemAccount()) {
            throw new \RuntimeException('Protected system accounts cannot be suspended.');
        }

        $this->update([
            'is_active' => false,
            'status' => \App\Enums\UserStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspended_by' => $by->id,
            'suspension_reason' => $reason,
        ]);
    }

    public function lock(): void
    {
        $this->update([
            'status' => \App\Enums\UserStatus::LOCKED,
            'locked_at' => now(),
        ]);
    }

    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'disabled_at' => null,
            'disabled_by' => null,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
            'locked_at' => null,
        ]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }
        if (app()->environment(['testing'])) {
            return true;
        }
        if (! $this->roles()->exists()) {
            return false;
        }
        if ($this->employee && ! $this->isEmployeeActive()) {
            return false;
        }
        $panelRoles = [
            'admin' => ['super_admin', 'admin', 'auditor', 'demo_observer'],
            'coordinator' => ['super_admin', 'admin', 'coordinator'],
        ];
        $allowedRoles = $panelRoles[$panel->getId()] ?? ['super_admin', 'admin', 'demo_observer'];

        return $this->hasAnyRole($allowedRoles);
    }

    public function getDashboardUrl(): string
    {
        $panels = \Filament\Facades\Filament::getPanels();

        $panelRoles = [
            'admin' => ['super_admin', 'admin', 'auditor', 'demo_observer'],
            'coordinator' => ['super_admin', 'admin', 'coordinator'],
        ];

        // Prefer admin if accessible
        if (isset($panels['admin']) && $this->hasAnyRole($panelRoles['admin'])) {
            return $panels['admin']->getUrl();
        }

        // Prefer coordinator if accessible
        if (isset($panels['coordinator']) && $this->hasAnyRole($panelRoles['coordinator'])) {
            return $panels['coordinator']->getUrl();
        }

        return url('/');
    }

    public function twoFactorAuthEnabled(): bool
    {
        return ! empty($this->app_authentication_secret) && ! empty($this->mfa_confirmed_at);
    }

    public function isMfaMandatoryByRole(): bool
    {
        $mandatoryRoles = config('security.mfa.mandatory_roles', ['super_admin', 'admin', 'custodian', 'auditor']);

        return $this->hasAnyRole($mandatoryRoles);
    }

    public function isMfaRequired(): bool
    {
        if ($this->mfa_enrollment_required) {
            return true;
        }

        return $this->isMfaMandatoryByRole();
    }

    public function mfaState(): string
    {
        if (empty($this->app_authentication_secret)) {
            return 'disabled';
        }

        if (empty($this->mfa_confirmed_at)) {
            return 'pending_enrollment';
        }

        return 'enabled';
    }

    public function isMfaVerifiedInSession(): bool
    {
        return session()->get('mfa_verified_user_id') === $this->id &&
               session()->has('mfa_verified_at');
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isProtectedSystemAccount()) {
                throw new \RuntimeException('Protected system accounts cannot be deleted.');
            }
        });

        static::created(function (User $user) {
            \App\Services\SecurityAuditService::log('USER_CREATED', "User account created for {$user->email}", auth()->user(), $user);
        });

        static::updating(function (User $user) {
            // Enforce Last Super Admin data invariant on update
            if ($user->isSuperAdmin()) {
                $willBeInactive = ($user->isDirty('is_active') && ! $user->is_active) ||
                                  ($user->isDirty('status') && $user->status !== \App\Enums\UserStatus::ACTIVE);

                if ($willBeInactive) {
                    if (static::getActiveSuperAdminCount() <= 1) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'status' => ['Unauthorized: Cannot disable, suspend, or deactivate the last active Super Admin.'],
                        ]);
                    }
                }
            }
        });

        static::updated(function (User $user) {
            if ($user->isDirty('status')) {
                $oldStatus = $user->getOriginal('status');
                $newStatus = $user->status;
                $event = match ($newStatus?->value) {
                    'active' => 'USER_ENABLED',
                    'suspended' => 'USER_SUSPENDED',
                    'disabled' => 'USER_DISABLED',
                    default => 'USER_STATUS_CHANGED',
                };
                \App\Services\SecurityAuditService::log(
                    $event,
                    "User {$user->email} status changed from ".($oldStatus?->value ?? 'null')." to {$newStatus?->value}",
                    auth()->user(),
                    $user,
                    ['old_status' => $oldStatus?->value, 'new_status' => $newStatus?->value]
                );
            }

            if ($user->isDirty('is_active') && ! $user->isDirty('status')) {
                $isActive = $user->is_active;
                $event = $isActive ? 'USER_ENABLED' : 'USER_DISABLED';
                \App\Services\SecurityAuditService::log(
                    $event,
                    "User {$user->email} active status set to ".($isActive ? 'true' : 'false'),
                    auth()->user(),
                    $user
                );
            }
        });

        static::deleting(function (User $user) {
            if ($user->isSuperAdmin()) {
                if (static::getActiveSuperAdminCount() <= 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'user' => ['Unauthorized: Cannot delete the last active Super Admin.'],
                    ]);
                }
            }
        });

        static::deleted(function (User $user) {
            \App\Services\SecurityAuditService::log('USER_DELETED', "User account deleted for {$user->email}", auth()->user(), $user);
        });
    }

    public function syncRoles(...$roles)
    {
        if ($this->exists && $this->isProtectedSystemAccount()) {
            $roleNames = collect($roles)->flatten()->map(function ($r) {
                if ($r instanceof \Spatie\Permission\Models\Role || $r instanceof Role) {
                    return $r->name;
                }
                if (is_string($r)) {
                    $roleObj = Role::where('uuid', $r)->orWhere('name', $r)->first();

                    return $roleObj ? $roleObj->name : $r;
                }

                return $r;
            })->toArray();

            if (! in_array('demo_observer', $roleNames, true) || count($roleNames) > 1) {
                throw new \RuntimeException('Protected system accounts must retain the demo_observer role and cannot be reassigned to other roles.');
            }
        }

        $rolesToSync = collect($roles)->flatten()->map(function ($role) {
            if ($role instanceof \Spatie\Permission\Models\Role) {
                return $role;
            }

            return \App\Models\Role::where('uuid', $role)->orWhere('name', $role)->first();
        })->filter();

        // Invariant: The last active Super Admin cannot lose the super_admin role
        if ($this->isSuperAdmin() && ! $rolesToSync->pluck('name')->contains('super_admin')) {
            if (static::getActiveSuperAdminCount() <= 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'roles' => ['Unauthorized: Cannot remove the Super Admin role from the last active Super Admin.'],
                ]);
            }
        }

        $oldRoles = $this->getRoleNames()->toArray();
        $result = $this->traitSyncRoles(...$roles);
        $newRoles = $this->getRoleNames()->toArray();

        if (array_diff($oldRoles, $newRoles) || array_diff($newRoles, $oldRoles)) {
            \App\Services\SecurityAuditService::log(
                'USER_ROLE_CHANGED',
                "Roles changed for user {$this->email}",
                auth()->user(),
                $this,
                ['old_roles' => $oldRoles, 'new_roles' => $newRoles]
            );
        }

        return $result;
    }

    public function assignRole(...$roles)
    {
        $oldRoles = $this->getRoleNames()->toArray();
        $result = $this->traitAssignRole(...$roles);
        $newRoles = $this->getRoleNames()->toArray();

        if (array_diff($oldRoles, $newRoles) || array_diff($newRoles, $oldRoles)) {
            \App\Services\SecurityAuditService::log(
                'USER_ROLE_CHANGED',
                "Roles assigned to user {$this->email}",
                auth()->user(),
                $this,
                ['old_roles' => $oldRoles, 'new_roles' => $newRoles]
            );
        }

        return $result;
    }

    public function removeRole($role)
    {
        if ($this->isProtectedSystemAccount()) {
            $roleName = $role instanceof \Spatie\Permission\Models\Role || $role instanceof Role ? $role->name : $role;
            if ($roleName === 'demo_observer') {
                throw new \RuntimeException('The demo_observer role cannot be removed from a protected system account.');
            }
        }

        $resolved = ($role instanceof \Spatie\Permission\Models\Role)
            ? $role
            : \App\Models\Role::where('uuid', $role)->orWhere('name', $role)->first();

        // Invariant: The last active Super Admin cannot lose the super_admin role
        if ($resolved && $resolved->name === 'super_admin' && $this->isSuperAdmin()) {
            if (static::getActiveSuperAdminCount() <= 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'roles' => ['Unauthorized: Cannot remove the Super Admin role from the last active Super Admin.'],
                ]);
            }
        }

        $oldRoles = $this->getRoleNames()->toArray();
        $result = $this->traitRemoveRole($role);
        $newRoles = $this->getRoleNames()->toArray();

        if (array_diff($oldRoles, $newRoles) || array_diff($newRoles, $oldRoles)) {
            \App\Services\SecurityAuditService::log(
                'USER_ROLE_CHANGED',
                "Role removed from user {$this->email}",
                auth()->user(),
                $this,
                ['old_roles' => $oldRoles, 'new_roles' => $newRoles]
            );
        }

        return $result;
    }
}
