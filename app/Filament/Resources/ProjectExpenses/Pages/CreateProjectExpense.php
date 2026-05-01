<?php

namespace App\Filament\Resources\ProjectExpenses\Pages;

use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use App\Services\ExpenseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectExpense extends CreateRecord
{
    protected static string $resource = ProjectExpenseResource::class;

    protected function afterCreate(): void
    {
        // Recalculate project budget
        app(ExpenseService::class)->recordExpense(
            $this->record->toArray(),
            auth()->id()
        );

        Notification::make()
            ->title('Expense recorded and budget updated')
            ->success()
            ->send();
    }
}
