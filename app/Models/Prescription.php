<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Prescription extends Model
{
    use HasUuids;

    protected $table = 'prescriptions';

    protected $fillable = [
        'doctor_name',
        'illness',
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
}
