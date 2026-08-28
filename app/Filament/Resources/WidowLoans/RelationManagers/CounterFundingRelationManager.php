<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

use App\Models\WidowLoan;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CounterFundingRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoan::class;

    protected static string $relationship = 'counterFundings';

    protected static ?string $title = 'Counter Funding History';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->weight('bold')
                    ->color('info')
                    ->summarize(
                        Sum::make()->money('NGN')
                    ),

                TextColumn::make('balance_before')
                    ->label('Balance Before')
                    ->money('NGN'),

                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->money('NGN'),

                TextColumn::make('notes')
                    ->label('Reason / Notes')
                    ->placeholder('No notes'),

                TextColumn::make('recorder.name')
                    ->label('Recorded By')
                    ->placeholder('System'),

                TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
