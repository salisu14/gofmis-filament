<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\MediaCollections\Models\Concerns\HasUuid;

class Category extends Model
{
    use HasUuid;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['created_at', 'updated_at'];

    public function items() : HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
