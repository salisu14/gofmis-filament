<?php

namespace App\Services\Deceased;

class PlaceOfDeathCatalog
{
    public const CANONICAL_PLACES = [
        'Hospital / Health Facility',
        'Home',
        'Workplace',
        'In Transit',
        'Other',
    ];

    public static function options(): array
    {
        return array_combine(self::CANONICAL_PLACES, self::CANONICAL_PLACES);
    }
}
