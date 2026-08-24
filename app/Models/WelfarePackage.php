<?php

namespace App\Models;

use App\Enums\WelfarePackageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class WelfarePackage extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => WelfarePackageStatus::class,
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $package) {
            if (empty($package->created_by) && auth()->check()) {
                $package->created_by = auth()->id();
            }
        });
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WelfarePackageItem::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(WelfareBeneficiary::class);
    }


    public function deceased()
    {
        return $this->hasManyThrough(
            Deceased::class,
            WelfareBeneficiary::class,
            'welfare_package_id', // FK on WelfareBeneficiary
            'id',                 // PK on Deceased
            'id',                 // PK on WelfarePackage
            'deceased_id'         // FK on WelfareBeneficiary → Deceased
        );
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', WelfarePackageStatus::OPEN);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', WelfarePackageStatus::DRAFT);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', WelfarePackageStatus::CLOSED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', WelfarePackageStatus::OPEN)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', WelfarePackageStatus::OPEN)
            ->where('start_date', '>', now());
    }

    // Accessors & Helpers
    public function isOpen(): bool
    {
        return $this->status === WelfarePackageStatus::OPEN;
    }

    public function isDraft(): bool
    {
        return $this->status === WelfarePackageStatus::DRAFT;
    }

    public function isClosed(): bool
    {
        return $this->status === WelfarePackageStatus::CLOSED;
    }

    public function isActive(): bool
    {
        return $this->isOpen()
            && $this->start_date <= now()
            && $this->end_date >= now();
    }

    /**
     * True only when the package is DRAFT (DRAFT → OPEN is the only open transition).
     */
    public function canBeOpened(): bool
    {
        return $this->isDraft();
    }

    /**
     * True only when the package is OPEN (OPEN → CLOSED).
     */
    public function canBeClosed(): bool
    {
        return $this->isOpen();
    }

    /**
     * True only when the package is CLOSED (CLOSED → OPEN reopen path).
     */
    public function canBeReopened(): bool
    {
        return $this->isClosed();
    }

    /**
     * True if at least one WelfareBeneficiary nomination exists for this package.
     */
    public function hasNominations(): bool
    {
        return $this->beneficiaries()->exists();
    }

    /**
     * Package composition (items, quantities) may be edited only when:
     *   - status is DRAFT, OR
     *   - status is OPEN AND no nominations have been made yet.
     *
     * Reopening a CLOSED package does NOT restore editability if nominations exist.
     */
    public function isCompositionEditable(): bool
    {
        if ($this->isDraft()) {
            return true;
        }

        if ($this->isOpen() && ! $this->hasNominations()) {
            return true;
        }

        return false;
    }

    public function approvedBeneficiaries(): Collection
    {
        return $this->beneficiaries()->approved()->get();
    }

    public function collectedCount(): int
    {
        return $this->beneficiaries()->collected()->count();
    }

    public function pendingCollectionCount(): int
    {
        return $this->beneficiaries()->approved()->notCollected()->count();
    }

    public function totalItemsValue(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity_per_family * ($item->item->unit_price ?? 0);
        });
    }
}
