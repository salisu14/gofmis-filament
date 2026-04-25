<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasUuids;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'name',
        'code',
        'type',
        // Add other fields as needed
    ];
}
