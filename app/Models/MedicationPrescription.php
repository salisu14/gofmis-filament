<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom Pivot Model to handle UUID generation for the many-to-many relationship.
 */
class MedicationPrescription extends Pivot
{
    use HasUuids;

    protected $table = 'medication_prescriptions';

    public $incrementing = false;

    protected $keyType = 'string';
}
