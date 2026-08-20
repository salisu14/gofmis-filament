<?php

namespace App\Filament\Resources\Verifications\Tables;

use App\Models\InterventionRequest;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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
                    ->description(fn ($record) => "Reg: {$record->orphan?->reg_no}"),

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

                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-m-check-badge')
                    ->color('info')
                    ->visible(fn (InterventionRequest $record) => in_array($record->status, ['pending', 'under_review'], true) && $record->verification_status !== 'verified')
                    ->modalHeading('Verify Education Request')
                    ->modalDescription(fn (InterventionRequest $record) => "Record verification findings for {$record->orphan?->full_name}.")
                    ->schema([
                        Textarea::make('verification_notes')
                            ->label('Verification Audit Notes')
                            ->placeholder('e.g. Verified with school principal, receipt authenticity confirmed...')
                            ->required()
                            ->rows(4),

                        FileUpload::make('verification_documents')
                            ->label('Supporting Evidence')
                            ->multiple()
                            ->directory('education-verifications')
                            ->disk('public')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*']),
                    ])
                    ->action(function (InterventionRequest $record, array $data) {
                        $record->markVerified(auth()->id(), $data['verification_notes']);

                        if (! empty($data['verification_documents'])) {
                            $record->update(['verification_documents' => $data['verification_documents']]);
                        }

                        Notification::make()
                            ->title('Request Verified')
                            ->body("Education request for {$record->orphan?->full_name} has been marked as verified.")
                            ->success()
                            ->send();
                    }),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Education Request')
                    ->modalDescription(fn (InterventionRequest $record) => "Approve education request for {$record->orphan?->full_name} (₦".number_format($record->requested_amount, 2).')?')
                    ->visible(fn (InterventionRequest $record) => $record->canApproveRequest())
                    ->action(function (InterventionRequest $record) {
                        $record->approveRequest(auth()->id());

                        Notification::make()
                            ->title('Request Approved')
                            ->body("Education request for {$record->orphan?->full_name} has been approved.")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Rejection')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (InterventionRequest $record) => $record->canRejectRequest())
                    ->action(function (InterventionRequest $record, array $data) {
                        $record->rejectRequest($data['rejection_reason'], auth()->id());

                        Notification::make()
                            ->title('Request Rejected')
                            ->body("Education request for {$record->orphan?->full_name} has been declined.")
                            ->danger()
                            ->send();
                    }),
            ])
            ->defaultSort('request_date', 'desc');
    }
}
