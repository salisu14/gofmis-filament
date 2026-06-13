<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DeceasedWelfare extends Pivot
{
    use HasUuids;

    protected $table = 'deceased_welfare';

    protected $fillable = [
        'welfare_id',
        'deceased_id',
        'collection_status',
    ];

    protected $casts = [
         'collection_status' => CollectionStatus::class,
    ];

    /**
     * If you ever need to access the deceased from the pivot record directly.
     */
    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class);
    }

    /**
     * If you ever need to access the welfare from the pivot record directly.
     */
    public function welfare(): BelongsTo
    {
        return $this->belongsTo(Welfare::class);
    }
}
