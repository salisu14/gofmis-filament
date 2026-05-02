<?php

namespace App\Filament\Exports;

use App\Models\WidowLoan;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class WidowLoanExporter extends Exporter
{
    protected static ?string $model = WidowLoan::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('widow.id'),
            ExportColumn::make('principal_amount'),
            ExportColumn::make('total_payable'),
            ExportColumn::make('total_paid'),
            ExportColumn::make('outstanding_balance'),
            ExportColumn::make('duration_months'),
            ExportColumn::make('repayment_frequency'),
            ExportColumn::make('status'),
            ExportColumn::make('disbursed_at'),
            ExportColumn::make('approval_flow_id'),
            ExportColumn::make('purpose'),
            ExportColumn::make('fully_repaid'),
            ExportColumn::make('loan_agreement_url'),
            ExportColumn::make('reject_reason'),
            ExportColumn::make('date_issued'),
            ExportColumn::make('due_date'),
            ExportColumn::make('created_at'),
            ExportColumn::make('deleted_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your widow loan export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
