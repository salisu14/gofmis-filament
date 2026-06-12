<?php

namespace App\Filament\Imprest\Resources\ImprestReplenishmentResource\Pages;

use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewImprestReplenishment extends ViewRecord
{
    protected static string $resource = ImprestReplenishmentResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn(): bool => $record->status === 'draft'),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-m-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn(): bool => $record->status === 'submitted' && auth()->user()->can('approve', $record->fund)
                )
                ->action(function () use ($record) {
                    try {
                        $service = app(ImprestReplenishmentServiceInterface::class);
                        $service->approve($record->id, auth()->id());

                        Notification::make()
                            ->title('Approved')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Approval failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('process')
                ->label('Process Payment')
                ->icon('heroicon-m-play')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Process Replenishment')
                ->modalDescription(fn (): string => $record->fund?->bank_account_id
                    ? 'Restore fund to authorized amount?'
                    : 'This fund is not linked to a bank account yet, so payment cannot be processed.')
                ->visible(fn(): bool => $record->status === 'approved' && auth()->user()->can('replenish', $record->fund)
                )
                ->disabled(fn (): bool => blank($record->fund?->bank_account_id))
                ->action(function () use ($record) {
                    try {
                        $service = app(ImprestReplenishmentServiceInterface::class);
                        $service->process($record->id);

                        Notification::make()
                            ->title('Processed')
                            ->success()
                            ->body('Fund balance restored.')
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Processing failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-m-x-mark')
                ->color('danger')
                ->schema([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->required()
                        ->label('Rejection Reason'),
                ])
                ->requiresConfirmation()
                ->visible(fn(): bool => in_array($record->status, ['submitted', 'approved']) &&
                    auth()->user()->can('approve', $record->fund)
                )
                ->action(function (array $data) use ($record) {
                    try {
                        $repo = app(\App\Repositories\Contracts\Imprest\ImprestReplenishmentRepositoryInterface::class);
                        $repo->reject($record->id, auth()->id(), $data['reason']);

                        Notification::make()
                            ->title('Rejected')
                            ->danger()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Rejection failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
