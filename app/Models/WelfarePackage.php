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

    public function canBeOpened(): bool
    {
        return $this->status->canTransitionTo(WelfarePackageStatus::OPEN);
    }

    public function canBeClosed(): bool
    {
        return $this->status->canTransitionTo(WelfarePackageStatus::CLOSED);
    }

    public function canBeReopened(): bool
    {
        return $this->isClosed();
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
