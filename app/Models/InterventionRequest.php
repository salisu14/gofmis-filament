<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterventionRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'orphan_id',
        'intervention_type_id',
        'status',
        'rejection_reason',
        'verification_status',
        'verified_by',
        'verified_at',
        'request_date'
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'request_date' => 'date',
    ];

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InterventionType::class, 'intervention_type_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterventionRequestItem::class);
    }

    // A request can generate one or more interventions (partial fulfillment)
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
