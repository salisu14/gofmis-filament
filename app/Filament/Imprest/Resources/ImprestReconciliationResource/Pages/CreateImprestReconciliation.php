<?php

namespace App\Filament\Imprest\Resources\ImprestReconciliationResource\Pages;

use App\Data\Imprest\ReconcileFundDto;
use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateImprestReconciliation extends CreateRecord
{
    protected static string $resource = ImprestReconciliationResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();

        $dto = new ReconcileFundDto(
            fundId: $data['fund_id'],
            reconciliationDate: \Carbon\Carbon::parse($data['reconciliation_date']),
            cashOnHand: (float)$data['cash_on_hand'],
            receiptsTotal: (float)($data['receipts_total'] ?? 0),
            auditorId: auth()->id(),
            custodianId: $data['custodian_id'],
            notes: $data['notes'] ?? null,
            varianceExplanation: $data['variance_explanation'] ?? null,
        );

        $service = app(ImprestReconciliationServiceInterface::class);

        try {
            $reconciliation = $service->reconcile($dto);

            $severity = $reconciliation->varianceSeverity();
            $title = $reconciliation->isBalanced() ? 'Reconciliation Complete' : 'Variance Detected';
            $color = $reconciliation->isBalanced() ? 'success' : 'warning';

            Notification::make()
                ->title($title)
                ->$color()
                ->body("Variance severity: " . ucfirst($severity))
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $reconciliation]));
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
        return null;
    }
}
