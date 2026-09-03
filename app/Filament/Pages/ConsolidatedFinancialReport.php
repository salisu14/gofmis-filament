<?php

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Services\ConsolidatedFinancialReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ConsolidatedFinancialReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Consolidated Financial Report';

    protected static ?string $title = 'Consolidated Financial Transactions & Expenditure Report';

    protected static ?string $slug = 'consolidated-financial-report';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.consolidated-financial-report';

    public ?array $data = [];

    public string $activeTab = 'all';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Coordinator is strictly forbidden
        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('coordinator')) {
            return false;
        }

        return $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->can('finance.consolidated_report.view')
            || $user->can('imprest_view_transactions')
            || (method_exists($user, 'isDemoObserver') && $user->isDemoObserver());
    }

    public function mount(): void
    {
        if (! static::canAccess()) {
            abort(403, 'Unauthorized access to consolidated financial report.');
        }

        $this->form->fill([
            'date_from' => null,
            'date_to' => null,
            'classification' => null,
            'type' => null,
            'bank_account_id' => null,
            'search' => null,
            'amount_min' => null,
            'amount_max' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('date_from')
                    ->label('Date From')
                    ->live(),
                DatePicker::make('date_to')
                    ->label('Date To')
                    ->live(),
                Select::make('classification')
                    ->label('Classification')
                    ->options([
                        ConsolidatedFinancialReportService::CLASSIFICATION_EXPENDITURE => 'A. EXPENDITURE',
                        ConsolidatedFinancialReportService::CLASSIFICATION_INCOME_RECEIPT => 'B. INCOME / RECEIPT',
                        ConsolidatedFinancialReportService::CLASSIFICATION_LOAN_MOVEMENT => 'C. ASSET / LOAN MOVEMENT',
                        ConsolidatedFinancialReportService::CLASSIFICATION_NON_CASH_DISTRIBUTION => 'D. NON-CASH DISTRIBUTION',
                        ConsolidatedFinancialReportService::CLASSIFICATION_FUNDING_TRANSFER => 'E. FUNDING / TRANSFER',
                        ConsolidatedFinancialReportService::CLASSIFICATION_HISTORICAL_DEPRECATED => 'F. HISTORICAL / DEPRECATED',
                    ])
                    ->placeholder('All Classifications')
                    ->live(),
                Select::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'intervention' => 'Intervention Payout',
                        'education_fee_payment' => 'Education Fee Payment',
                        'loan_disbursement' => 'Loan Disbursement',
                        'loan_repayment' => 'Loan Repayment',
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'transfer' => 'Internal Transfer',
                        'imprest_funding' => 'Historical Imprest Funding',
                        'imprest_replenishment' => 'Historical Imprest Replenishment',
                        'imprest_expense' => 'Historical Imprest Expense',
                    ])
                    ->placeholder('All Types')
                    ->live(),
                Select::make('bank_account_id')
                    ->label('Bank Account')
                    ->options(fn () => BankAccount::query()->pluck('account_name', 'id')->toArray())
                    ->placeholder('All Bank Accounts')
                    ->live(),
                TextInput::make('search')
                    ->label('Search Reference / Description')
                    ->placeholder('Reference or keyword...')
                    ->live(debounce: 500),
                TextInput::make('amount_min')
                    ->label('Min Amount (₦)')
                    ->numeric()
                    ->live(debounce: 500),
                TextInput::make('amount_max')
                    ->label('Max Amount (₦)')
                    ->numeric()
                    ->live(debounce: 500),
            ])
            ->columns(4)
            ->statePath('data');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function getKpisProperty(): array
    {
        $service = app(ConsolidatedFinancialReportService::class);

        return $service->getKpis($this->data ?? [], $this->activeTab);
    }

    public function table(Table $table): Table
    {
        $service = app(ConsolidatedFinancialReportService::class);

        return $table
            ->query(fn () => $service->getTransactionsQuery($this->data ?? [], $this->activeTab))
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('classification')
                    ->label('Classification')
                    ->badge()
                    ->state(fn (Transaction $record) => ConsolidatedFinancialReportService::classifyType($record->type, $record->isInternalTransfer()))
                    ->color(fn (string $state) => match ($state) {
                        ConsolidatedFinancialReportService::CLASSIFICATION_EXPENDITURE => 'danger',
                        ConsolidatedFinancialReportService::CLASSIFICATION_INCOME_RECEIPT => 'success',
                        ConsolidatedFinancialReportService::CLASSIFICATION_LOAN_MOVEMENT => 'warning',
                        ConsolidatedFinancialReportService::CLASSIFICATION_NON_CASH_DISTRIBUTION => 'info',
                        ConsolidatedFinancialReportService::CLASSIFICATION_FUNDING_TRANSFER => 'gray',
                        ConsolidatedFinancialReportService::CLASSIFICATION_HISTORICAL_DEPRECATED => 'secondary',
                        default => 'primary',
                    }),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->tooltip(fn (Transaction $record) => $record->description),
                TextColumn::make('bankAccount.account_name')
                    ->label('Bank Account')
                    ->default('N/A'),
                TextColumn::make('debit_amount')
                    ->label('Debit / Outflow (₦)')
                    ->state(fn (Transaction $record) => $record->isDebitType() ? number_format((float) $record->amount, 2) : '—')
                    ->color('danger'),
                TextColumn::make('credit_amount')
                    ->label('Credit / Inflow (₦)')
                    ->state(fn (Transaction $record) => $record->isCreditType() ? number_format((float) $record->amount, 2) : '—')
                    ->color('success'),
                TextColumn::make('amount')
                    ->label('Amount (₦)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('transactionable_type')
                    ->label('Source Module')
                    ->state(fn (Transaction $record) => $record->transactionable_type ? class_basename($record->transactionable_type) : 'Direct Ledger'),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([15, 25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'isDemoObserver') && $user->isDemoObserver()) {
            return [];
        }

        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $user = auth()->user();
                    if ($user && method_exists($user, 'isDemoObserver') && $user->isDemoObserver()) {
                        abort(403, 'Demo Observer cannot export reports.');
                    }

                    $service = app(ConsolidatedFinancialReportService::class);

                    return $service->exportCsv($this->data ?? [], $this->activeTab);
                }),

            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->url(fn () => route('reports.consolidated-financial-report.pdf', array_merge($this->data ?? [], ['mode' => $this->activeTab])))
                ->openUrlInNewTab(),
        ];
    }
}
