<?php

namespace App\Models;

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
        'illness_id',        // ← normalized reference
        'lab_test_cost',
        'drug_cost',
        'prescription_date',
        'note',
        'prescribable_id',
        'prescribable_type',
        'user_id'
    ];

    protected $casts = [
        'lab_test_cost' => 'decimal:2',
        'drug_cost' => 'decimal:2',
        'prescription_date' => 'date',
    ];

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
}
