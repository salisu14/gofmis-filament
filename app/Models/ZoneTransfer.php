<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneTransfer extends Model
{
    use HasUuids;

    protected $table = 'zone_transfers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'deceased_id',
        'from_zone_id',
        'to_zone_id',
        'moved_by',
        'reason',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the deceased individual who was moved.
     */
    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class);
    }

    /**
     * Get the zone the family moved from.
     */
    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'from_zone_id');
    }

    /**
     * Get the zone the family moved to.
     */
    public function toZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'to_zone_id');
    }

    /**
     * Get the staff member who recorded the transfer.
     */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
