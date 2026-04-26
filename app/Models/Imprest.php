<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Imprest extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'authorized_amount',
        'current_balance',
        'custodian_id',
        'start_date',
        'is_active',
    ];

    protected $casts = [
        'authorized_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'start_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(ImprestTransaction::class);
    }

    public function replenishments(): HasMany
    {
        return $this->hasMany(ImprestReplenishment::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function getTotalSpentAttribute(): float
    {
        return $this->transactions()->sum('total_amount');
    }

    public function getBalanceAttribute(): float
    {
        return $this->authorized_amount - $this->total_spent;
    }
}
