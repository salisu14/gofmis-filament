<?php

namespace App\Filament\Imprest\Pages\Reports;

use App\Models\ImprestFund;
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

class FundUtilizationReportO extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|null|\UnitEnum $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Fund Utilization';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.imprest.pages.reports.fund-utilization';

    public ?array $data = [];

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
                    ->when($fundId, fn ($q) => $q->where('fund_id', $fundId))
                    ->whereBetween('date', [$start, $end])
                    ->where('status', 'active')
                    ->with('fund')
            )
            ->columns([
                TextColumn::make('date')->date(),
                TextColumn::make('voucher_no')->searchable(),
                TextColumn::make('name')->label('Deceased')->searchable(),
                TextColumn::make('item_service')->limit(30),
                TextColumn::make('category')->badge(),
                TextColumn::make('total_price')->money('USD')->alignment('right'),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function generateReport(): void
    {
        $this->resetTable();
    }
}
