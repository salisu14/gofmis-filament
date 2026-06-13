<?php

namespace App\Filament\Imprest\Resources\ImprestReconciliationResource\Pages;

use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewImprestReconciliation extends ViewRecord
{
    protected static string $resource = ImprestReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn(): bool => $record->status === 'in_progress'),

            Action::make('acknowledge')
                ->label('Acknowledge')
                ->icon('heroicon-m-hand-thumb-up')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn(): bool => !$record->custodian_acknowledged &&
                    auth()->id() === $record->custodian_id
                )
                ->action(function () use ($record) {
                    $service = app(ImprestReconciliationServiceInterface::class);
                    $service->acknowledge($record->id, auth()->id());

                    Notification::make()
                        ->title('Acknowledged')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                }),

            Action::make('complete')
                ->label('Mark Complete')
                ->icon('heroicon-m-check')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn(): bool => $record->status === 'in_progress' &&
                    auth()->user()->can('reconcile', $record->fund)
                )
                ->action(function () use ($record) {
                    $service = app(ImprestReconciliationServiceInterface::class);
                    $service->complete($record->id);

                    Notification::make()
                        ->title('Completed')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                }),

            Action::make('flag')
                ->label('Flag for Review')
                ->icon('heroicon-m-flag')
                ->color('danger')
                ->schema([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->required()
                        ->label('Flag Reason'),
                ])
                ->requiresConfirmation()
                ->visible(fn(): bool => $record->status === 'in_progress' &&
                    auth()->user()->can('reconcile', $record->fund)
                )
                ->action(function (array $data) use ($record) {
                    $service = app(ImprestReconciliationServiceInterface::class);
                    $service->flag($record->id, $data['reason']);

                    Notification::make()
                        ->title('Flagged')
                        ->warning()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record->fresh()]));
                }),
        ];
    }
}
