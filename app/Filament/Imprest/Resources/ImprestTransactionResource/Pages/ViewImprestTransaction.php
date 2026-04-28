<?php

namespace App\Filament\Imprest\Resources\ImprestTransactionResource\Pages;

use App\Data\Imprest\ApproveTransactionDto;
use App\Data\Imprest\VoidTransactionDto;
use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Services\Contracts\Imprest\ImprestTransactionServiceInterface;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewImprestTransaction extends ViewRecord
{
    protected static string $resource = ImprestTransactionResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn(): bool => $record->status === 'pending'),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Transaction')
                ->modalDescription('Approve this transaction and deduct from fund balance?')
                ->visible(fn(): bool => $record->status === 'pending' && auth()->user()->can('approve', $record)
                )
                ->action(function () use ($record) {
                    $service = app(ImprestTransactionServiceInterface::class);
                    $service->approve(new ApproveTransactionDto(
                        transactionId: $record->id,
                        approvedBy: auth()->id(),
                    ));

                    Notification::make()
                        ->title('Approved')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                }),

            Action::make('void')
                ->label('Void')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->required()
                        ->minLength(10)
                        ->label('Void Reason'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Void Transaction')
                ->visible(fn(): bool => $record->isVoidable() && auth()->user()->can('void', $record)
                )
                ->action(function (array $data) use ($record) {
                    $service = app(ImprestTransactionServiceInterface::class);
                    $service->void(new VoidTransactionDto(
                        transactionId: $record->id,
                        voidedBy: auth()->id(),
                        reason: $data['reason'],
                    ));

                    Notification::make()
                        ->title('Voided')
                        ->danger()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                }),

            Actions\DeleteAction::make()
                ->visible(fn(): bool => $record->status === 'pending' && auth()->user()->hasRole('admin')
                ),
        ];
    }
}
