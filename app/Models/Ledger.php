<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ledger extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'type'];

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
