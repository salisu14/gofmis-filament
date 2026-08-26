<?php

namespace App\Filament\Actions;

use App\Models\Prescription;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Throwable;

class MarkHealthcareTreatedAction
{
    public static function make(): Action
    {
        return Action::make('markTreated')
            ->label('Mark as Treated')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Mark Healthcare Request as Treated')
            ->modalDescription('This will mark the healthcare request as completed and lock the record from further modification.')
            ->schema([
                Section::make('Treatment Completion')
                    ->schema([
                        DatePicker::make('treated_at')
                            ->label('Treatment Completion Date')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->closeOnDateSelection(),

                        Textarea::make('treatment_notes')
                            ->label('Treatment Outcome & Administration Notes')
                            ->rows(3)
                            ->placeholder('Enter details regarding medication administration, patient response, or medical outcome...')
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (Prescription $record, array $data): void {
                try {
                    $record->markAsTreated(
                        notes: $data['treatment_notes'] ?? null,
                        treatedAt: $data['treated_at'] ?? null,
                        treatedByUserId: auth()->id()
                    );

                    Notification::make()
                        ->success()
                        ->title('Healthcare Request Marked as Treated')
                        ->body("Healthcare treatment for {$record->prescribable?->full_name} has been marked as completed.")
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Action Failed')
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn (Prescription $record) => $record->isPending()
                && (
                    auth()->user()?->hasAnyRole(['admin', 'super_admin'])
                    || auth()->user()?->can('treat_healthcare_requests')
                )
            );
    }
}
