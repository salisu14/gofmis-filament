<?php

namespace App\Filament\Imprest\Resources\ImprestTransactionResource\Pages;

use App\Data\Imprest\CreateTransactionDto;
use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Services\Contracts\Imprest\ImprestTransactionServiceInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateImprestTransaction extends CreateRecord
{
    protected static string $resource = ImprestTransactionResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();

        $dto = new CreateTransactionDto(
            fundId: $data['fund_id'],
            date: \Carbon\Carbon::parse($data['date']),
            deceasedId: $data['deceased_id'],
            name: $data['name'] ?? null,
            expenseType: $data['expense_type'] ?? 'service',
            itemId: $data['item_id'] ?? null,
            serviceDescription: $data['service_description'] ?? null,
            itemService: $data['item_service'] ?? null,
            quantity: (float)$data['quantity'],
            unitPrice: (float)$data['unit_price'],
            category: TransactionCategory::from($data['category']),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            receiptAttached: $data['receipt_attached'] ?? false,
            voucherNo: $data['voucher_no'] ?? null,
        );

        $service = app(ImprestTransactionServiceInterface::class);

        try {
            $transaction = $service->create($dto, auth()->id());

            Notification::make()
                ->title('Transaction Created')
                ->success()
                ->body("Voucher {$transaction->voucher_no} created pending approval.")
                ->send();

            if ($another) {
                $this->form->fill();
                return;
            }

            $this->redirect($this->getResource()::getUrl('view', ['record' => $transaction]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null; // Handled manually above
    }
}
