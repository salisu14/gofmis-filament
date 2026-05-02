<?php

namespace App\Filament\Exports;

use App\Models\InterventionRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class InterventionRequestExporter extends Exporter
{
    protected static ?string $model = InterventionRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('orphan.id'),
            ExportColumn::make('intervention_type_id'),
            ExportColumn::make('rejection_reason'),
            ExportColumn::make('status'),
            ExportColumn::make('request_date'),
            ExportColumn::make('verification_status'),
            ExportColumn::make('requested_at'),
            ExportColumn::make('reviewed_by'),
            ExportColumn::make('reviewed_at'),
            ExportColumn::make('approved_by'),
            ExportColumn::make('approved_at'),
            ExportColumn::make('verified_by'),
            ExportColumn::make('verified_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('verification_notes'),
            ExportColumn::make('verification_documents'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your intervention request export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
