<?php

namespace App\Filament\Imprest\Resources\ImprestTransactionResource\Pages;

use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Models\ImprestFund;
use App\Models\ImprestTransaction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditImprestTransaction extends EditRecord
{
    protected static string $resource = ImprestTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn(ImprestTransaction $record): bool => $record->status === 'pending' && auth()->user()->hasRole('admin')
                ),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Only allow editing certain fields if pending
        $record = $this->getRecord();

        if ($record->status !== 'pending') {
            // Remove protected fields
            unset($data['fund_id'], $data['quantity'], $data['unit_price'], $data['total_price']);

            return $data;
        }

        if (blank($data['deceased_id'] ?? null) && blank($data['name'] ?? null)) {
            throw ValidationException::withMessages([
                'data.name' => 'Enter a payee or beneficiary name when the transaction is not linked to a deceased family.',
            ]);
        }

        $fund = ImprestFund::query()->find($data['fund_id'] ?? $record->fund_id);
        $amount = (float) ($data['quantity'] ?? $record->quantity) * (float) ($data['unit_price'] ?? $record->unit_price);

        if ($fund && ($amount > (float) $fund->current_balance || $amount > (float) $fund->authorized_amount)) {
            throw ValidationException::withMessages([
                'data.unit_price' => 'Transaction exceeds the available fund balance. Available: ₦'
                    .number_format((float) $fund->current_balance, 2)
                    .', Required: ₦'.number_format($amount, 2),
            ]);
        }

        return $data;
    }

    protected function canEdit(): bool
    {
        $record = $this->getRecord();
        return $record->status === 'pending' && auth()->user()->can('update', $record);
    }
}
