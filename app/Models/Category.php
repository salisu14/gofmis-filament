<?php

/* -------------------------------------------------------------------------
 | 1. THE IMPROVED CATEGORY MODEL
 ------------------------------------------------------------------------- */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'user_id'
    ];

    /* -----------------------------
     | Relationships
     ------------------------------*/

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the sub-categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get items associated with this category.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * The staff member who managed this category.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
