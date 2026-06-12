<?php

namespace App\Filament\Imprest\Pages\Reports;

use App\Models\ImprestFund;
use App\Models\ImprestTransaction;
use App\Services\Company\CompanyInformationService;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class FundUtilizationReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|null|\UnitEnum $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Fund Utilization';
    protected static ?int $navigationSort = 1;
    public ?array $data = [];

    protected string $view = 'filament.imprest.pages.reports.fund-utilization';

    public function mount(): void
    {
        $this->form->fill([
            'fund_id' => null,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('fund_id')
                    ->label('Fund')
                    ->options(ImprestFund::active()->pluck('location', 'id'))
                    ->searchable()
                    ->preload()
                    ->native(false),

                DatePicker::make('start_date')
                    ->label('From')
                    ->native(false)
                    ->required(),

                DatePicker::make('end_date')
                    ->label('To')
                    ->native(false)
                    ->required()
                    ->after('start_date'),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $fundId = $this->data['fund_id'] ?? null;
        $start = $this->data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $this->data['end_date'] ?? now()->endOfMonth()->toDateString();

        return $table
            ->query(
                \App\Models\ImprestTransaction::query()
                    ->when($fundId, fn($q) => $q->where('fund_id', $fundId))
                    ->whereBetween('date', [$start, $end])
                    ->where('status', 'active')
                    ->with('fund')
            )
            ->columns([
                TextColumn::make('date')->date(),
                TextColumn::make('voucher_no')->searchable(),
                TextColumn::make('beneficiary_name')->label('Deceased')->searchable(['name']),
                TextColumn::make('expense_description')->label('Item / Service')->limit(30)->searchable(['item_service', 'service_description']),
                TextColumn::make('category')->badge(),
                TextColumn::make('total_price')->money('NGN')->alignment('right'),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function generateReport(): void
    {
        // This triggers re-render with new table data
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Report')
                ->icon('heroicon-m-arrow-path')
                ->action('generateReport'),

            Action::make('download_pdf')
                ->label('Download FUR PDF')
                ->icon('heroicon-m-printer')
                ->color('success')
                ->action('downloadPdf'),
        ];
    }

    public function downloadPdf()
    {
        $start = $this->data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $this->data['end_date'] ?? now()->endOfMonth()->toDateString();
        $fundId = $this->data['fund_id'] ?? null;
        $fund = $fundId ? ImprestFund::with(['custodian', 'bankAccount', 'zone'])->find($fundId) : null;

        $transactions = $this->reportQuery($fundId, $start, $end)
            ->with(['fund.custodian', 'fund.bankAccount', 'deceased', 'item', 'custodian', 'approver'])
            ->orderBy('date')
            ->get();

        $summary = [
            'total_spent' => (float) $transactions->sum('total_price'),
            'transaction_count' => $transactions->count(),
            'authorized_amount' => $fund ? (float) $fund->authorized_amount : (float) ImprestFund::sum('authorized_amount'),
            'current_balance' => $fund ? (float) $fund->current_balance : (float) ImprestFund::sum('current_balance'),
            'missing_receipts' => $transactions->where('receipt_attached', false)->count(),
        ];

        $pdf = Pdf::loadView('filament.imprest.reports.fund-utilization-pdf', [
            'fund' => $fund,
            'transactions' => $transactions,
            'summary' => $summary,
            'startDate' => $start,
            'endDate' => $end,
            'generatedBy' => auth()->user(),
            'generatedAt' => now(),
            'company' => app(CompanyInformationService::class)->reportHeader(),
        ])->setPaper('a4', 'landscape');

        $filename = 'FUR-'.($fund?->location ? str($fund->location)->slug() : 'all-funds').'-'.now()->format('Ymd-His').'.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    protected function getSummaryData(): ?array
    {
        if (!isset($this->data['fund_id'])) {
            return null;
        }

        $service = app(ImprestReconciliationServiceInterface::class);
        return $service->getReconciliationReport(
            $this->data['fund_id'],
            $this->data['start_date'],
            $this->data['end_date']
        );
    }

    private function reportQuery(?string $fundId, string $start, string $end): \Illuminate\Database\Eloquent\Builder
    {
        return ImprestTransaction::query()
            ->when($fundId, fn($q) => $q->where('fund_id', $fundId))
            ->whereBetween('date', [$start, $end])
            ->where('status', 'active');
    }
}
