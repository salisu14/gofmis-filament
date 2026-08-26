<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Models\IdCard;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewIdCard extends ViewRecord
{
    protected static string $resource = IdCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (IdCard $record): bool => static::getResource()::canEdit($record)),

            Action::make('activate_single')
                ->label('Issue / Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate ID Card')
                ->modalDescription('This will issue and activate this ID card credential.')
                ->modalSubmitActionLabel('Yes, Activate')
                ->visible(fn (IdCard $record): bool => $record->status === 'draft')
                ->action(function (IdCard $record): void {
                    if (! $record->beneficiaryIsEligible()) {
                        Notification::make()
                            ->title('Cannot Activate Card')
                            ->body('This beneficiary is not currently eligible for an active ID card.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->activate();

                    Notification::make()
                        ->title('ID Card Activated')
                        ->body("Card {$record->card_number} is now active.")
                        ->success()
                        ->send();
                }),

            Action::make('replace')
                ->label('Replace / Reissue')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Replace ID Card')
                ->modalDescription('This will revoke the current active card and generate a new active replacement card. The original card history will be preserved.')
                ->modalSubmitActionLabel('Replace Card')
                ->schema([
                    Textarea::make('reason')
                        ->label('Replacement Reason')
                        ->placeholder('e.g. Card reported lost / damaged by beneficiary')
                        ->required()
                        ->minLength(10),
                ])
                ->visible(fn (IdCard $record): bool => $record->status === 'active')
                ->action(function (IdCard $record, array $data): void {
                    $beneficiary = $record->cardable;

                    if (! $beneficiary || ! $record->beneficiaryIsEligible()) {
                        Notification::make()
                            ->title('Cannot Replace Card')
                            ->body('Beneficiary is not currently eligible for a replacement ID card.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->revoke('Replaced: '.$data['reason']);

                    $genService = app(\App\Services\IdCardGenerationService::class);
                    $newCard = $genService->generateCard($beneficiary, $record->template, false);
                    $newCard->activate();

                    Notification::make()
                        ->title('ID Card Replaced')
                        ->body("Old card {$record->card_number} revoked. New replacement card {$newCard->card_number} issued and activated.")
                        ->success()
                        ->send();
                }),

            Action::make('preview')
                ->label('Preview PDF')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (IdCard $record): string => route('id-cards.preview', ['card' => $record]))
                ->openUrlInNewTab(),

            Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (IdCard $record) => route(
                    'id-cards.download',
                    ['idCard' => $record]
                ))
                ->openUrlInNewTab(),

            Action::make('revoke')
                ->label('Revoke Card')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke ID Card')
                ->modalDescription('This will permanently invalidate this ID card.')
                ->modalSubmitActionLabel('Yes, Revoke')
                ->schema([
                    Textarea::make('reason')
                        ->label('Revocation Reason')
                        ->required()
                        ->minLength(10),
                ])
                ->action(function (IdCard $record, array $data) {
                    $record->revoke($data['reason']);
                })
                ->visible(fn (IdCard $record): bool => $record->status === 'active'),

            Action::make('reactivate')
                ->label('Reactivate Card')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Reactivate ID Card')
                ->modalDescription('This will restore the ID card back to an active state.')
                ->modalSubmitActionLabel('Yes, Reactivate')
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason for Reactivation')
                        ->required()
                        ->minLength(10),
                ])
                ->action(function (IdCard $record, array $data): void {
                    if (! $record->beneficiaryIsEligible()) {
                        Notification::make()
                            ->title('Card Cannot Be Reactivated')
                            ->body('This beneficiary is not currently eligible for an active ID card.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->reactivate();

                    Notification::make()
                        ->title('Card Reactivated')
                        ->success()
                        ->send();
                })
                ->visible(fn (IdCard $record): bool => $record->status === 'revoked'),
        ];
    }
}
