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

    protected static function booted(): void
    {
        static::deleting(function (WelfareBeneficiary $model) {
            if (! $model->isPending()) {
                throw new \DomainException('Processed or collected welfare allocation records cannot be deleted.');
            }
        });
    }

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
        if (! $this->canBeCollected()) {
            return false;
        }

        $updated = $this->update([
            'collection_status' => CollectionStatus::COLLECTED,
            'collected_at' => now(),
            'collected_by' => $collectedBy ?? auth()->id(),
            'collection_notes' => $notes,
        ]);

        if ($updated) {
            $packageItems = $this->welfarePackage?->items;
            if ($packageItems) {
                foreach ($packageItems as $pkgItem) {
                    \App\Models\StockMovement::firstOrCreate([
                        'item_id' => $pkgItem->item_id,
                        'movement_type' => \App\Enums\StockMovementType::WELFARE_ISSUE,
                        'reference_type' => self::class,
                        'reference_id' => $this->id,
                    ], [
                        'quantity' => -1 * (int) $pkgItem->quantity_per_family,
                        'occurred_at' => now(),
                        'created_by' => $collectedBy ?? auth()->id(),
                        'notes' => "Welfare Package Collection ({$this->welfarePackage?->name})",
                    ]);
                }
            }

            \App\Events\BeneficiaryCollected::dispatch($this, $notes);
        }

        return $updated;
    }

    /**
     * Whether the household still has at least one operational AND eligible
     * widow/orphan. Eligibility is revalidated immediately before collection.
     */
    public function householdStillEligible(): bool
    {
        $deceased = $this->deceased;

        if (! $deceased) {
            return false;
        }

        $hasWidow = $deceased->widows->contains(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible);
        $hasOrphan = $deceased->orphans->contains(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible);

        return $hasWidow || $hasOrphan;
    }

    /**
     * Stock movement rows that would be posted for this collection.
     */
    public function collectionStockMovements(): array
    {
        $movements = [];

        foreach ($this->welfarePackage?->items ?? [] as $pkgItem) {
            $movements[] = [
                'item_id' => $pkgItem->item_id,
                'quantity' => -1 * (int) $pkgItem->quantity_per_family,
            ];
        }

        return $movements;
    }

    /**
     * Canonical single-record collection operation.
     *
     * Enforces:
     *  - the beneficiary is APPROVED and NOT_COLLECTED;
     *  - the household is revalidated as eligible at collection time;
     *  - sufficient stock exists on the canonical ledger before posting;
     *  - all writes happen inside the caller-provided transaction.
     *
     * @throws \RuntimeException when collection is not permitted.
     */
    public function collect(string $notes = null, ?string $collectedBy = null): bool
    {
        if (! $this->canBeCollected()) {
            throw new \RuntimeException('This package cannot be collected. Ensure beneficiary is approved and not already collected.');
        }

        if (! $this->householdStillEligible()) {
            throw new \RuntimeException('This household is no longer eligible for welfare support. Collection is blocked.');
        }

        $this->assertStockAvailable();

        $result = $this->markAsCollected($notes, $collectedBy);

        if (! $result) {
            throw new \RuntimeException('Failed to record welfare collection.');
        }

        return true;
    }

    /**
     * Verify the canonical stock ledger has sufficient available stock for
     * every item in the package. Uses the same ledger-derived semantics as
     * StockAvailabilityService (on-hand minus reserved, per item).
     *
     * @throws \RuntimeException when any item would go negative.
     */
    public function assertStockAvailable(): void
    {
        $package = $this->welfarePackage;

        if (! $package) {
            throw new \RuntimeException('Welfare package not found for this allocation.');
        }

        foreach ($package->items as $pkgItem) {
            $itemId = $pkgItem->item_id;
            $required = (int) $pkgItem->quantity_per_family;

            $onHand = (int) \App\Models\StockMovement::where('item_id', $itemId)->sum('quantity');
            $reserved = (int) \App\Models\WelfarePackageItem::join('welfare_beneficiaries', 'welfare_package_items.welfare_package_id', '=', 'welfare_beneficiaries.welfare_package_id')
                ->where('welfare_package_items.item_id', $itemId)
                ->where('welfare_beneficiaries.status', \App\Enums\BeneficiaryStatus::APPROVED->value)
                ->where('welfare_beneficiaries.collection_status', \App\Enums\CollectionStatus::NOT_COLLECTED->value)
                ->whereNull('welfare_beneficiaries.deleted_at')
                ->sum('welfare_package_items.quantity_per_family');

            $available = $onHand - $reserved;

            if ($available < $required) {
                $itemName = $pkgItem->item?->name ?? 'Unknown item';
                throw new \RuntimeException("Insufficient stock for [{$itemName}] to fulfil this collection.");
            }
        }
    }

    public function markAsApproved(?string $approvedBy = null): bool
    {
        if (! $this->canBeApproved()) {
            return false;
        }

        return $this->update([
            'status' => BeneficiaryStatus::APPROVED,
            'approved_by' => $approvedBy ?? auth()->id(),
        ]);
    }

    public function markAsRejected(string $reason, ?string $rejectedBy = null): bool
    {
        if (! $this->canBeRejected()) {
            return false;
        }

        return $this->update([
            'status' => BeneficiaryStatus::REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => $rejectedBy ?? auth()->id(),
        ]);
    }
}
