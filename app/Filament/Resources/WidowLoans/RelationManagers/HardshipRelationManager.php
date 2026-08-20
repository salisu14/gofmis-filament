<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

use App\Models\WidowLoanHardshipCase;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HardshipRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoanHardshipCase::class;

    protected static string $relationship = 'hardshipCases';

    protected static ?string $title = 'Hardship Relief Cases';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Reported On')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reporter.name')
                    ->label('Reported By')
                    ->placeholder('System'),

                TextColumn::make('reason_category')
                    ->label('Category')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('reason_details')
                    ->label('Details')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('verifier.name')
                    ->label('Verified By')
                    ->placeholder('Pending'),

                TextColumn::make('verified_at')
                    ->label('Verified On')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('Pending'),

                TextColumn::make('approved_at')
                    ->label('Approved On')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('relief_window')
                    ->label('Relief Window')
                    ->state(function (WidowLoanHardshipCase $record) {
                        $relief = $record->reliefPeriods()->first();
                        if (! $relief) {
                            return 'No Relief Period';
                        }

                        return "{$relief->starts_at->format('M d, Y')} — {$relief->ends_at->format('M d, Y')}";
                    })
                    ->badge()
                    ->color(fn ($state) => str_contains($state, 'No Relief') ? 'gray' : 'info'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
