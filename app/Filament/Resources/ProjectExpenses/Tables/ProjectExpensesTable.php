<?php

namespace App\Filament\Resources\ProjectExpenses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('milestone.title')
                    ->placeholder('General Expense')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'materials' => 'warning',
                        'labor' => 'info',
                        'transport' => 'gray',
                        'permits' => 'danger',
                        'equipment' => 'primary',
                        'utilities' => 'success',
                        default => 'secondary',
                    }),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('expense_date')
                    ->date('M d, Y')
                    ->sortable(),

                IconColumn::make('receipt_path')
                    ->label('Receipt')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success'),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category')
                    ->options([
                        'materials' => 'Materials',
                        'labor' => 'Labor/Wages',
                        'transport' => 'Transport',
                        'permits' => 'Permits/Fees',
                        'equipment' => 'Equipment',
                        'utilities' => 'Utilities',
                        'other' => 'Other',
                    ]),

                TernaryFilter::make('has_receipt')
                    ->label('Has Receipt')
                    ->placeholder('Any')
                    ->trueLabel('With Receipt')
                    ->falseLabel('No Receipt')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('receipt_path'),
                        false: fn ($query) => $query->whereNull('receipt_path'),
                    ),

                Filter::make('expense_date')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn ($query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
