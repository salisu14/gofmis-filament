<?php
// app/Filament/Widgets/WelfareInterventionWidget.php

namespace App\Filament\Widgets;

use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WelfareInterventionWidget extends BaseWidget
{
    protected static ?string $heading = 'Welfare Interventions';
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WelfareBeneficiary::query()
                    ->with(['deceased.zone', 'welfarePackage', 'suggester', 'approver'])
                    ->whereIn('status', ['approved', 'collected'])
            )
            ->heading('Welfare Support Beneficiaries')
            ->description(fn() => 'Total welfare packages: ' . WelfarePackage::count() .
                ' | Total beneficiaries: ' . WelfareBeneficiary::count() .
                ' | Approved: ' . WelfareBeneficiary::approved()->count() .
                ' | Collected: ' . WelfareBeneficiary::collected()->count())
            ->columns([
                TextColumn::make('deceased.full_name')
                    ->label('Family Head')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('welfarePackage.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deceased.zone.name')
                    ->label('Zone')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'collected',
                    ]),

                IconColumn::make('collection_status')
                    ->label('Collected')
                    ->boolean(),

                TextColumn::make('collected_at')
                    ->date('M d, Y')
                    ->placeholder('Not collected'),

                TextColumn::make('suggester.name')
                    ->label('Suggested By')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }
}
