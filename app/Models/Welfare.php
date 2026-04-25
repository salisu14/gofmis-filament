<?php

namespace App\Models;

use App\Enums\WelfareStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Welfare extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'date', 'collection_status', 'welfare_status'
    ];

    protected $casts = [
        'date' => 'date',
        'welfare_status' => WelfareStatus::class,
    ];

    public function deceased(): BelongsToMany
    {
        return $this->belongsToMany(Deceased::class, 'welfare_deceased')
            ->withPivot('collection_status')
            ->withTimestamps();
    }
}
