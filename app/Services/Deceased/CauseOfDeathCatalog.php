<?php

namespace App\Services\Deceased;

class CauseOfDeathCatalog
{
    public const CANONICAL_CAUSES = [
        'Natural Causes',
        'Hypertension / Cardiovascular',
        'Stroke / Cerebrovascular',
        'Diabetes / Endocrine',
        'Cancer / Oncology',
        'Respiratory Disease / Pneumonia',
        'Kidney / Renal Failure',
        'Liver Disease',
        'Road Traffic Accident / Trauma',
        'Infectious Disease / Malaria / Typhoid',
        'Short Illness',
        'Old Age',
        'Other',
    ];

    public static function options(): array
    {
        return array_combine(self::CANONICAL_CAUSES, self::CANONICAL_CAUSES);
    }

    public static function isCanonical(?string $cause): bool
    {
        if (empty($cause)) {
            return false;
        }

        return in_array($cause, self::CANONICAL_CAUSES, true);
    }
}
