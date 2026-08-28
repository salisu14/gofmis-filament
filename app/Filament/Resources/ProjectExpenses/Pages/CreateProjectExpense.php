<?php

namespace App\Filament\Resources\ProjectExpenses\Pages;

use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectExpense extends CreateRecord
{
    protected static string $resource = ProjectExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->check()) {
            $data['recorded_by'] = auth()->id();
        }

        return $data;
    }
}
