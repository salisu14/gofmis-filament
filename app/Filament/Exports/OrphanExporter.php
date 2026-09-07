<?php

namespace App\Filament\Exports;

use App\Models\Orphan;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class OrphanExporter extends Exporter
{
    protected static ?string $model = Orphan::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reg_no'),
            ExportColumn::make('nin'),
            ExportColumn::make('first_name'),
            ExportColumn::make('middle_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('full_name'),
            ExportColumn::make('gender'),
            ExportColumn::make('birth_date'),
            ExportColumn::make('age'),
            ExportColumn::make('status'),
            ExportColumn::make('is_married'),
            ExportColumn::make('married_at'),
            ExportColumn::make('address'),
            ExportColumn::make('deceased.reg_no')
                ->label('Deceased/Family Reg No'),
            ExportColumn::make('deceased.full_name')
                ->label('Deceased/Family Name'),
            ExportColumn::make('zone.name')
                ->label('Zone'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your orphan export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
