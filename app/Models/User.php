<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    // Zone assignment for coordinators
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    // Zone this user coordinates (if they are a coordinator)
    public function coordinatedZone(): HasOne
    {
        return $this->hasOne(Zone::class, 'coordinator_id');
    }

    // Spatie role helpers
    public function isCoordinator(): bool
    {
        return $this->hasRole('coordinator');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function managesZone(?string $zoneId = null): bool
    {
        if (!$this->isCoordinator()) {
            return false;
        }

        if ($zoneId === null) {
            return $this->zone_id !== null;
        }

        return $this->zone_id === $zoneId;
    }

    // Get zone ID for filtering (works for both coordinators and admins)
    public function effectiveZoneId(): ?string
    {
        if ($this->isCoordinator()) {
            return $this->zone_id;
        }

        return null; // Admins see all zones
    }
}
