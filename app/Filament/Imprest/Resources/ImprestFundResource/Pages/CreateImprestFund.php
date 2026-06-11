<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\Pages;

use App\Filament\Imprest\Resources\ImprestFundResource;
use App\Models\BankAccount;
use App\Models\ImprestFund;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\CreateRecord;

class CreateImprestFund extends CreateRecord
{
    protected static string $resource = ImprestFundResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Fund created successfully';
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): ImprestFund {
            $data['current_balance'] = $data['authorized_amount'];

            $bankAccount = BankAccount::lockForUpdate()->findOrFail($data['bank_account_id']);
            $bankAccount->debit((float) $data['authorized_amount']);

            $fund = ImprestFund::create($data);

            Transaction::create([
                'bank_account_id' => $bankAccount->id,
                'transactionable_type' => ImprestFund::class,
                'transactionable_id' => $fund->id,
                'reference' => 'IMPF-'.strtoupper(substr($fund->id, 0, 8)),
                'date' => now(),
                'type' => 'imprest_funding',
                'amount' => $data['authorized_amount'],
                'description' => "Initial imprest funding for {$fund->location}",
                'is_system' => true,
            ]);

            return $fund;
        });
    }
}
