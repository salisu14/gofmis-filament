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
        'intervention_type_id',
        'bank_account_id',
        'amount',
        'status',
        'disbursed_at',
        'disbursed_by',
        'collected_by',
        'collected_at',
        'notes',
        'support_document_url',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        // Specify the foreign key 'intervention_request_id' to override Laravel's default 'request_id'
        return $this->belongsTo(InterventionRequest::class, 'intervention_request_id');
    }

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterventionItem::class);
    }
}
