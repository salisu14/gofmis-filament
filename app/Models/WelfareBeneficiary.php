<?php

namespace App\Models;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WelfareBeneficiary extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'welfare_package_id',
        'deceased_id',
        'suggested_by',
        'approved_by',
        'status',
        'rejection_reason',
        'collection_status',
        'collected_at',
        'collected_by',
        'collection_notes',
    ];

    protected $casts = [
        'status' => BeneficiaryStatus::class,
        'collection_status' => CollectionStatus::class,
        'approved_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    // Relationships
    public function welfarePackage(): BelongsTo
    {
        return $this->belongsTo(WelfarePackage::class);
    }

    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class);
    }

    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', BeneficiaryStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', BeneficiaryStatus::APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', BeneficiaryStatus::REJECTED);
    }

    public function scopeCollected($query)
    {
        return $query->where('collection_status', CollectionStatus::COLLECTED);
    }

    public function scopeNotCollected($query)
    {
        return $query->where('collection_status', CollectionStatus::NOT_COLLECTED);
    }

    public function scopeReadyForCollection($query)
    {
        return $query->approved()->notCollected();
    }

    public function scopeForPackage($query, string $packageId)
    {
        return $query->where('welfare_package_id', $packageId);
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === BeneficiaryStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === BeneficiaryStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === BeneficiaryStatus::REJECTED;
    }

    public function isCollected(): bool
    {
        return $this->collection_status === CollectionStatus::COLLECTED;
    }

    public function isNotCollected(): bool
    {
        return $this->collection_status === CollectionStatus::NOT_COLLECTED;
    }
    public function canBeCollected(): bool
    {
        return $this->status === BeneficiaryStatus::APPROVED
            && $this->collection_status === CollectionStatus::NOT_COLLECTED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === BeneficiaryStatus::PENDING;
    }

    public function canBeRejected(): bool
    {
        return $this->status === BeneficiaryStatus::PENDING;
    }

    public function markAsCollected(?string $notes = null, ?string $collectedBy = null): bool
    {
        if (!$this->canBeCollected()) {
            return false;
        }

        return $this->update([
            'collection_status' => CollectionStatus::COLLECTED,
            'collected_at' => now(),
            'collected_by' => $collectedBy ?? auth()->id(),
            'collection_notes' => $notes,
        ]);
    }

    public function markAsApproved(?string $approvedBy = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        return $this->update([
            'status' => BeneficiaryStatus::APPROVED,
            'approved_by' => $approvedBy ?? auth()->id(),
        ]);
    }

    public function markAsRejected(string $reason, ?string $rejectedBy = null): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        return $this->update([
            'status' => BeneficiaryStatus::REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => $rejectedBy ?? auth()->id(),
        ]);
    }
}
