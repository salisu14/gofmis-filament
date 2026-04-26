<?php

namespace App\Filament\Resources\WidowLoans\Tables;

use App\Filament\Actions\ApproveWidowLoanAction;
use App\Filament\Actions\RejectWidowLoanAction;
use App\Filament\Actions\SubmitForApprovalAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WidowLoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('widow.full_name')
                    ->label('Widow Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('principal_amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('duration_months')
                    ->label('Duration (Months)')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'info' => 'approved',
                        'danger' => 'rejected',
                        'primary' => 'disbursed',
                        'success' => 'completed',
                        'danger' => 'defaulted',
                    ])
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('Paid')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('purpose')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'disbursed' => 'Disbursed',
                        'completed' => 'Completed',
                        'defaulted' => 'Defaulted',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                SubmitForApprovalAction::make(),
                ApproveWidowLoanAction::make(),
                RejectWidowLoanAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
