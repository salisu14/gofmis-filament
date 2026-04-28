<?php
// app/Filament/Widgets/SpecialInterventionWidget.php

namespace App\Filament\Widgets;

use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SpecialInterventionWidget extends BaseWidget
{
    protected static ?string $heading = 'Special Interventions';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WelfarePackage::query()
                    ->withCount('beneficiaries')
                    ->with(['beneficiaries.deceased'])
            )

            ->heading('Welfare Distribution Summary (By Package)')

            ->description(fn() =>
                'Total welfare packages: ' . WelfarePackage::count() .
                ' | Total beneficiaries: ' . WelfareBeneficiary::count() .
                ' | Collected: ' . WelfareBeneficiary::collected()->count()
            )

            ->columns([

                TextColumn::make('name')
                    ->label('Package Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->date('M d, Y')
                    ->label('Start Date')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->date('M d, Y')
                    ->label('End Date')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('beneficiaries_count')
                    ->label('Families Benefited')
                    ->numeric()
                    ->sortable(),

                // Orphans count (derived correctly)
                TextColumn::make('orphans_reached')
                    ->label('Orphans Reached')
                    ->getStateUsing(function ($record) {
                        return $record->beneficiaries
                            ->sum(fn($b) => $b->deceased->orphans_count ?? 0);
                    }),

                // Widows count (derived correctly)
                TextColumn::make('widows_reached')
                    ->label('Widows Reached')
                    ->getStateUsing(function ($record) {
                        return $record->beneficiaries
                            ->sum(fn($b) => $b->deceased->widows_count ?? 0);
                    }),

                TextColumn::make('created_at')
                    ->date('M d, Y')
                    ->sortable(),
            ])

            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }
}
