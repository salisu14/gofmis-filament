<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Prescription extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'prescriptions';

    protected $fillable = [
        'doctor_name',
        'illness',
        'illness_id',        // ← normalized reference
        'lab_test_cost',
        'drug_cost',
        'prescription_date',
        'note',
        'prescribable_id',
        'prescribable_type',
        'user_id',
        'status',
        'treated_at',
        'treated_by_id',
        'treatment_notes',
    ];

    protected $casts = [
        'lab_test_cost' => 'decimal:2',
        'drug_cost' => 'decimal:2',
        'prescription_date' => 'date',
        'status' => PrescriptionStatus::class,
        'treated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Prescription $model) {
            $model->status ??= PrescriptionStatus::PENDING;
        });

        static::deleting(function (Prescription $model) {
            if ($model->isTreated()) {
                throw new \DomainException('Completed healthcare and treatment records cannot be deleted.');
            }
        });
    }

    public static function totalCostQuery(): \Illuminate\Database\Query\Expression|\Illuminate\Contracts\Database\Query\Expression
    {
        return DB::raw('COALESCE(lab_test_cost, 0) + COALESCE(drug_cost, 0)');
    }

    // Polymorphic: The Patient (Orphan or Widow)
    public function prescribable(): MorphTo
    {
        return $this->morphTo();
    }

    // The Staff who prescribed
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // The Staff who marked treatment as completed
    public function treatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treated_by_id');
    }

    // Normalized illness reference
    public function illnessModel(): BelongsTo
    {
        return $this->belongsTo(Illness::class, 'illness_id');
    }

    // The drugs prescribed
    /**
     * Updated to use the custom pivot model MedicationPrescription.
     */
    public function medications(): BelongsToMany
    {
        return $this->belongsToMany(Medication::class, 'medication_prescriptions')
            ->using(MedicationPrescription::class)
            ->withTimestamps();
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->lab_test_cost + (float) $this->drug_cost;
    }

    /**
     * Accessor for backward compatibility.
     * Returns the normalized illness name, falling back to the legacy text field.
     */
    public function getIllnessNameAttribute(): ?string
    {
        return $this->illnessModel?->name ?? $this->illness;
    }

    public function isTreated(): bool
    {
        $statusValue = $this->status instanceof PrescriptionStatus ? $this->status : PrescriptionStatus::tryFrom((string) $this->status);

        return $statusValue === PrescriptionStatus::TREATED || ! is_null($this->treated_at);
    }

    public function isPending(): bool
    {
        return ! $this->isTreated() && $this->status !== PrescriptionStatus::CANCELLED;
    }

    public function markAsTreated(?string $notes = null, ?string $treatedAt = null, ?string $treatedByUserId = null): void
    {
        if ($this->isTreated()) {
            throw new \DomainException('This healthcare request has already been marked as treated.');
        }

        $this->update([
            'status' => PrescriptionStatus::TREATED,
            'treated_at' => $treatedAt ? \Carbon\Carbon::parse($treatedAt) : now(),
            'treated_by_id' => $treatedByUserId ?? auth()->id(),
            'treatment_notes' => $notes ?? $this->treatment_notes,
        ]);
    }
}
