<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\Pages;

use App\Filament\Imprest\Exports\TransactionExporter;
use App\Filament\Imprest\Resources\ImprestFundResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListImprestFunds extends ListRecords
{
    protected static string $resource = ImprestFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-m-plus'),

            ExportAction::make()
                ->exporter(TransactionExporter::class)
                ->fileName(fn(): string => 'transactions-' . now()->format('Y-m-d')),
        ];
    }
}
