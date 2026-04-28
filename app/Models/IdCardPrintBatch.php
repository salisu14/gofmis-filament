<?php
// app/Models/IdCardPrintBatch.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdCardPrintBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_name',
        'type',
        'filters',
        'range',
        'total_count',
        'processed_count',
        'pdf_path',
        'status',
        'started_at',
        'completed_at',
        'created_by'
    ];

    protected $casts = [
        'filters' => 'array',
        'range' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progressPercentage(): int
    {
        if ($this->total_count === 0) return 0;
        return (int) round(($this->processed_count / $this->total_count) * 100);
    }
}
