<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class WelfarePackageItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'welfare_package_id',
        'item_id',
        'quantity_per_family',
        'notes',
    ];

    protected $casts = [
        'quantity_per_family' => 'integer',
    ];

    // Relationships
    public function welfarePackage(): BelongsTo
    {
        return $this->belongsTo(WelfarePackage::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function category(): HasOneThrough
    {
        return $this->hasOneThrough(
            Category::class,
            Item::class,
            'id',          // Foreign key on items table...
            'id',          // Foreign key on categories table...
            'item_id',     // Local key on welfare_package_items table...
            'category_id'  // Local key on items table...
        );
    }

    // Scope
    public function scopeForPackage($query, string $packageId)
    {
        return $query->where('welfare_package_id', $packageId);
    }
}
