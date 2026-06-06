<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
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

    /* ─────────────────────────────────────────
       ZONE / COORDINATOR RELATIONSHIP
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

    /**
     * Can this user act as a coordinator (has the role)?
     */
    public function isCoordinator(): bool
    {
        return $this->hasRole('coordinator');
    }

    /**
     * Is this user actually assigned to coordinate a specific zone?
     */
    public function isAssignedCoordinator(): bool
    {
        return $this->isCoordinator() && $this->hasZone();
    }

    /**
     * Does this user manage the given zone?
     * If no zone is passed, checks if they manage ANY zone.
     */
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

    /**
     * Get the zone ID this user is restricted to.
     * Returns null for non-coordinators (admins see all).
     */
    public function restrictedZoneId(): ?string
    {
        return $this->isCoordinator() ? $this->zoneId() : null;
    }

    /* ─────────────────────────────────────────
       ROLE HELPERS
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
}
