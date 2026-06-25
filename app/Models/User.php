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

class User extends Authenticatable implements
    FilamentUser,
    HasAvatar,
    HasAppAuthentication,
    HasAppAuthenticationRecovery
{
    use HasFactory, HasRoles, HasUuids, Notifiable;
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
        'app_authentication_secret',
        'app_authentication_recovery_codes',
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

    public function hasElevatedPrivileges(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
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
            'admin' => ['super_admin', 'admin'],
            'coordinator' => ['super_admin', 'admin', 'coordinator'],
        ];
        $allowedRoles = $panelRoles[$panel->getId()] ?? ['super_admin', 'admin'];
        return $this->hasAnyRole($allowedRoles);
    }

    public function twoFactorAuthEnabled(): bool
    {
        return !empty($this->app_authentication_secret);
    }
}
