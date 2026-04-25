<?php

namespace App\Services;

use App\Models\Deceased;
use App\Models\Welfare;

class WelfareService
{
    /**
     * Identify most vulnerable families (Priority 'A') for shelter/material support.
     */
    public function getMostVulnerableFamilies()
    {
        return Deceased::where('vulnerability_status', 'A')
            ->whereHas('welfare', function ($q) {
                $q->where('welfare_status', 'OPENED');
            })
            ->with('widows', 'orphans')
            ->get();
    }
}
