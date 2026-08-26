<?php

namespace App\Filament\Resources\Verifications\Tables;

use App\Filament\Actions\ApproveInterventionRequestAction;
use App\Filament\Actions\RejectInterventionRequestAction;
use App\Filament\Actions\StartInterventionRequestReviewAction;
use App\Filament\Actions\VerifyEducationRequestAction;
use Filament\Actions\ViewAction;
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
                        'fulfilled' => 'success',
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
                StartInterventionRequestReviewAction::make(),
                VerifyEducationRequestAction::make(),
                ApproveInterventionRequestAction::make(),
                RejectInterventionRequestAction::make(),
            ])
            ->defaultSort('request_date', 'desc');
    }
}
