<?php
// app/Filament\Coordinator\Resources\WelfareRequestResource/Pages/ListWelfareRequests.php

namespace App\Filament\Coordinator\Resources\WelfareRequestResource\Pages;

use App\Filament\Coordinator\Resources\WelfareRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWelfareRequests extends ListRecords
{
    protected static string $resource = WelfareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Welfare Request'),
        ];
    }
}
