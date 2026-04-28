<?php

namespace App\Filament\Resources\ApprovalFlows\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApprovalFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Flow ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model_type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => str($state)->afterLast('\\')->singular())
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('current_step')
                    ->label('Step')
                    ->formatStateUsing(fn($state, $record) => "{$state} / {$record->total_steps}")
                    ->sortable(),
                TextColumn::make('approvalSteps')
                    ->label('Progress')
                    ->badge()
                    ->formatStateUsing(function ($record) {
                        $steps = $record->approvalSteps ?? collect();
                        $approved = $steps->where('status', 'approved')->count();
                        return "{$approved}/{$record->total_steps} approved";
                    }),
                TextColumn::make('approver.name')
                    ->label('Final Approver')
                    ->sortable(),
                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('model_type')
                    ->options([
                        'App\Models\WidowLoan' => 'Widow Loans',
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
