<?php
// app/Filament/Widgets/LoanRepaymentWidget.php

namespace App\Filament\Widgets;

use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LoanRepaymentWidget extends BaseWidget
{
    protected static ?string $heading = 'Loan Repayments';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Widow::query()
                    ->whereHas('widowLoans.repayments')
                    ->withCount(['widowLoans as total_loans' => fn($q) => $q->where('status', '!=', 'rejected')])
                    ->withSum([
                        'widowLoans as total_principal' => fn($q) => $q->where('status', '!=', 'rejected')
                    ], 'principal_amount')
                    ->withSum([
                        'widowLoans as total_repaid_via_loan' => fn($q) => $q->where('status', '!=', 'rejected')
                    ], 'total_paid')
                    // Alternative: sum repayments directly through relationship
                    ->with([
                        'widowLoans' => fn($q) => $q->withSum('repayments', 'amount')
                    ])
            )
            ->heading('Widow Loan Repayments')
            ->description(fn() => 'Total repaid across all widows: ₦' . number_format((float) WidowLoanRepayment::sum('amount'), 2) .
                ' | Total repayments: ' . WidowLoanRepayment::count() .
                ' | This month: ₦' . number_format((float) WidowLoanRepayment::whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)->sum('amount'), 2))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Widow Name')
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

                TextColumn::make('total_repaid_via_loan')
                    ->label('Repaid via Loan (₦)')
                    ->numeric(2)
                    ->money('NGN')
                    ->color('success'),

                TextColumn::make('widow_loans_sum_repayments_amount')
                    ->label('Direct Repayment Sum (₦)')
                    ->numeric(2)
                    ->money('NGN')
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('repayment_progress')
                    ->label('Progress')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $principal = (float) $record->total_principal;
                        $repaid = (float) $record->total_repaid_via_loan;
                        if ($principal <= 0) return 'N/A';
                        $percentage = round(($repaid / $principal) * 100, 1);
                        return $percentage . '%';
                    })
                    ->colors([
                        'danger' => fn($state) => str_replace('%', '', $state) < 30,
                        'warning' => fn($state) => str_replace('%', '', $state) >= 30 && str_replace('%', '', $state) < 70,
                        'success' => fn($state) => str_replace('%', '', $state) >= 70,
                    ]),

                TextColumn::make('outstanding')
                    ->label('Outstanding (₦)')
                    ->numeric(2)
                    ->money('NGN')
                    ->color('danger')
                    ->getStateUsing(fn($record) =>
                    max(0, (float) $record->total_principal - (float) $record->total_repaid_via_loan)
                    ),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_repayments')
                    ->label('Has Repayments')
                    ->query(fn(Builder $query) => $query->whereHas('widowLoans', fn($q) => $q->has('repayments'))),

                Tables\Filters\Filter::make('fully_repaid')
                    ->label('Fully Repaid')
                    ->query(function (Builder $query) {
                        $query->whereHas('widowLoans', function ($q) {
                            $q->whereColumn('total_paid', '>=', 'total_payable');
                        });
                    }),

                Tables\Filters\Filter::make('has_outstanding')
                    ->label('Has Outstanding')
                    ->query(function (Builder $query) {
                        $query->whereHas('widowLoans', function ($q) {
                            $q->whereColumn('total_paid', '<', 'total_payable')
                                ->where('status', '!=', 'rejected');
                        });
                    }),

                Tables\Filters\Filter::make('repaid_this_month')
                    ->label('Repaid This Month')
                    ->query(function (Builder $query) {
                        $query->whereHas('widowLoans.repayments', function ($q) {
                            $q->whereMonth('paid_at', now()->month)
                                ->whereYear('paid_at', now()->year);
                        });
                    }),
            ])
            ->defaultSort('total_repaid_via_loan', 'desc')
            ->paginated([10, 25, 50]);
    }
}
