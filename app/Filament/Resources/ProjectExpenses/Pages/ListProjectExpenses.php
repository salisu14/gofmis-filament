<?php

namespace App\Filament\Resources\ProjectExpenses\Pages;

use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectExpenses extends ListRecords
{
    protected static string $resource = ProjectExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
