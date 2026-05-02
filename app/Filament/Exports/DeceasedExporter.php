<?php

namespace App\Filament\Exports;

use App\Models\Deceased;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class DeceasedExporter extends Exporter
{
    protected static ?string $model = Deceased::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('first_name'),
            ExportColumn::make('middle_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('full_name')
            ->label('FullName'),
            ExportColumn::make('nin')
            ->label('NIN'),
            ExportColumn::make('reg_no')
            ->label('RegNo'),
            ExportColumn::make('number_of_orphans_left'),
            ExportColumn::make('number_of_widows_left'),
            ExportColumn::make('age')
            ->label('Age'),
            ExportColumn::make('guardian_name'),
            ExportColumn::make('guardian_phone'),
            ExportColumn::make('deceased_age'),
            ExportColumn::make('address'),
            ExportColumn::make('vulnerability_status')
            ->label('Vulnerability Status'),
            ExportColumn::make('date_registered'),
            ExportColumn::make('death_cause'),
            ExportColumn::make('death_place'),
            ExportColumn::make('occupation'),
            ExportColumn::make('has_death_cert'),
            ExportColumn::make('death_cert_url'),
            ExportColumn::make('zone.name'),
//            ExportColumn::make('deleted_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your deceased export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
