<?php

namespace App\Filament\Imprest\Tables\Columns;

use Filament\Tables\Columns\Column;

class VarianceIndicatorColumn extends Column
{
    protected string $view = 'filament.imprest.tables.columns.variance-indicator';

    public function getSeverity(): string
    {
        $record = $this->getRecord();
        $authorized = $record->fund?->authorized_amount ?? 1;
        $percentage = abs($record->actual_variance) / $authorized * 100;

        return match (true) {
            $percentage < 0.5 => 'negligible',
            $percentage < 2.0 => 'minor',
            $percentage < 5.0 => 'moderate',
            default => 'critical',
        };
    }
}
