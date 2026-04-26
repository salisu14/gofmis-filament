<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\Pages;

use App\Filament\Imprest\Resources\ImprestFundResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewImprestFund extends ViewRecord
{
    protected static string $resource = ImprestFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('print_report')
                ->label('Print Report')
                ->icon('heroicon-m-printer')
//                ->url(fn ($record) => route('imprest.fund.report', $record))
                ->openUrlInNewTab(),
        ];
    }
}
