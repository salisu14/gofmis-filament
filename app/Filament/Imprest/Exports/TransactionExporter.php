<?php

namespace App\Filament\Imprest\Exports;

use App\Models\ImprestTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TransactionExporter extends Exporter
{
    protected static ?string $model = ImprestTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('voucher_no'),
            ExportColumn::make('date'),
            ExportColumn::make('deceased_id'),
            ExportColumn::make('name'),
            ExportColumn::make('item_service'),
            ExportColumn::make('category'),
            ExportColumn::make('quantity'),
            ExportColumn::make('unit_price'),
            ExportColumn::make('total_price'),
            ExportColumn::make('payment_method'),
            ExportColumn::make('status'),
            ExportColumn::make('custodian.name'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your transaction export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
