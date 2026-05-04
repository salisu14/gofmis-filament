<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TransactionLine;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_account_id',
        'reference',
        'description',
        'amount',
        'type',
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }
}
