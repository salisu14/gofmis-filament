<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIdCards extends ListRecords
{
    protected static string $resource = IdCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('bulk_generator')
                ->label('Bulk Generate')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(fn(): string => IdCardResource::getUrl('bulk-print')),
        ];
    }
}
