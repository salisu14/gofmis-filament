<?php
// app/Filament/Widgets/LoanBeneficiariesWidget.php

namespace App\Filament\Widgets;

use App\Models\Widow;
use App\Models\WidowLoan;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LoanBeneficiariesWidget extends BaseWidget
{
    protected static ?string $heading = 'Loan Beneficiaries';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Widow::query()
                    ->whereHas('widowLoans', fn($q) => $q->where('status', '!=', 'rejected'))
                    ->withCount(['widowLoans as total_loans' => fn($q) => $q->where('status', '!=', 'rejected')])
                    ->withSum(['widowLoans as total_principal' => fn($q) => $q->where('status', '!=', 'rejected')], 'principal_amount')
                    ->withSum(['widowLoans as total_repaid' => fn($q) => $q->where('status', '!=', 'rejected')], 'total_paid')
            )
            ->heading('Loan Beneficiaries Summary')
            ->description(fn() => 'Total beneficiaries: ' . Widow::whereHas('widowLoans')->count() .
                ' | Total principal: ₦' . number_format((float) WidowLoan::where('status', '!=', 'rejected')->sum('principal_amount'), 2))
            ->columns([
                TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reg_no')
                    ->label('Reg. No')
                    ->searchable(),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable(),

                TextColumn::make('total_loans')
                    ->label('No. of Loans')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_principal')
                    ->label('Total Principal (₦)')
                    ->numeric(2)
                    ->money('NGN'),

                TextColumn::make('total_repaid')
                    ->label('Total Repaid (₦)')
                    ->numeric(2)
                    ->money('NGN')
                    ->color('success'),

                TextColumn::make('widowLoans.status')
                    ->label('Latest Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => is_array($state) ? end($state) : $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'completed',
                        'info' => 'disbursed',
                    ]),
            ])
            ->defaultSort('total_principal', 'desc')
            ->paginated([5, 10, 25]);
    }
}
