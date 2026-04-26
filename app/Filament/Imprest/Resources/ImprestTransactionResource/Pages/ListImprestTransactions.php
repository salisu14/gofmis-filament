<?php

namespace App\Filament\Imprest\Resources\ImprestTransactionResource\Pages;

use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Filament\Imprest\Exports\TransactionExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImprestTransactions extends ListRecords
{
    protected static string $resource = ImprestTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-m-plus'),

            \Filament\Actions\ExportAction::make()
                ->exporter(TransactionExporter::class)
                ->fileName(fn (): string => 'transactions-' . now()->format('Y-m-d'))
                ->icon('heroicon-m-arrow-down-tray'),
        ];
    }
}
