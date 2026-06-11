<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterventionRequestItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'intervention_request_id',
        'item_id',
        'item_name',
        'specification',
        'quantity_requested',
        'orphan_class',
        'quantity_fulfilled'
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_fulfilled' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InterventionType::class, 'intervention_type_id');
    }
    public function request(): BelongsTo
    {
        return $this->belongsTo(InterventionRequest::class);
    }

    // Link to the actual items given against this request
    public function interventionItems(): HasMany
    {
        return $this->hasMany(InterventionItem::class, 'intervention_request_item_id');
    }

    // Helper to check if fully fulfilled
    public function getIsFullyFulfilledAttribute(): bool
    {
        return $this->quantity_fulfilled >= $this->quantity_requested;
    }

    // Add to booted() to auto-snapshot the name
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!empty($model->item_id) && empty($model->item_name)) {
                $model->item_name = $model->item?->name;
            }
        });
    }
}
