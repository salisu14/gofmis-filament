<?php

namespace App\Filament\Exports;

use App\Models\Widow;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class WidowExporter extends Exporter
{
    protected static ?string $model = Widow::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('first_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('middle_name'),
            ExportColumn::make('nin'),
            ExportColumn::make('reg_no'),
            ExportColumn::make('skills'),
            ExportColumn::make('is_married'),
            ExportColumn::make('address'),
            ExportColumn::make('full_name'),
            ExportColumn::make('deceased.id'),
//            ExportColumn::make('deleted_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your widow export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
