<?php

namespace App\Filament\Resources\Verifications\Tables;

use App\Models\InterventionRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EducationVerificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orphan.full_name')
                    ->label('Beneficiary')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => "Reg: {$record->orphan?->reg_no}"),

                TextColumn::make('orphan.deceased.zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type.name')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('requested_amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->default(0.00)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'under_review' => 'info',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('verification_status')
                    ->label('Verification')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'in_progress' => 'info',
                        'failed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('verifier.name')
                    ->label('Verifier')
                    ->placeholder('Pending')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('request_date')
                    ->label('Submitted')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status'),
                SelectFilter::make('verification_status'),
                SelectFilter::make('zone')
                    ->relationship('orphan.deceased.zone', 'name')
                    ->label('Filter by Zone'),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->label('Verify')
                    ->icon('heroicon-m-check-badge')
                    ->color('primary')
                    ->visible(fn($record) => $record->status === 'pending' && $record->verification_status === 'failed'),

                ActionGroup::make([
                    Action::make('quick_approve')
                        ->label('Quick Approve')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Quick Approval')
                        ->modalDescription(fn($record) => "Approve education request for {$record->orphan?->full_name} and skip detailed audit?")
                        ->visible(fn($record) => in_array($record->status, ['pending', 'under_review']))
                        ->action(function (InterventionRequest $record) {
                            $record->markVerified(auth()->id());
                            $record->approveRequest(auth()->id());

                            Notification::make()
                                ->title('Request Approved')
                                ->body("The request for {$record->orphan?->full_name} has been marked as verified.")
                                ->success()
                                ->send();
                        }),

                    Action::make('quick_reject')
                        ->label('Quick Reject')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Quick Rejection')
                        ->schema([
                            Textarea::make('rejection_reason')
                                ->label('Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->visible(fn($record) => in_array($record->status, ['pending', 'under_review']))
                        ->action(function (InterventionRequest $record, array $data) {
                            $record->rejectRequest($data['rejection_reason'], auth()->id());

                            Notification::make()
                                ->title('Request Rejected')
                                ->body("The request for {$record->orphan?->full_name} has been declined.")
                                ->danger()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('request_date', 'desc');
    }
}
