<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intervention extends Model
{
    use HasUuids;

    protected $fillable = [
        'intervention_request_id',
        'orphan_id',
        'date_given',
        'collected_by',
        'collected_at',
        'notes',
        'document_url'
    ];

    protected $casts = [
        'date_given' => 'date',
        'collected_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(InterventionRequest::class);
    }

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterventionItem::class);
    }
}
