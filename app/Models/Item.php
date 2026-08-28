<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'user_id',
        'unit_of_measure',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Current stock is derived from the canonical StockMovement ledger.
     *
     * The ledger uses SIGNED quantities: inflows (OPENING_BALANCE, purchases,
     * donations, adjustments in, returns in) are positive, outflows
     * (WELFARE_ISSUE, INTERVENTION_ISSUE, ADJUSTMENT_OUT, LOSS_DAMAGE) are
     * negative — the same convention as WelfareBeneficiary::markAsCollected()
     * and assertStockAvailable(). Current stock is therefore simply the signed
     * sum, clamped to zero. This keeps the Item list, Stock Availability and
     * welfare distribution all reading ONE authoritative source.
     */
    public function getCurrentStockAttribute(): int
    {
        return max(0, (int) $this->stockMovements()->sum('quantity'));
    }
}
