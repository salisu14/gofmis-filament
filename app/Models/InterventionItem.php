<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'intervention_id',
        'intervention_request_item_id', // Critical for tracking what was fulfilled
        'item_name',
        'specification',
        'quantity',
        'unit_value',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_value' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    // The specific request item this "fulfills"
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(InterventionRequestItem::class, 'intervention_request_item_id');
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!empty($model->item_id) && empty($model->item_name)) {
                $model->item_name = $model->item?->name;
            }
        });
    }
}
