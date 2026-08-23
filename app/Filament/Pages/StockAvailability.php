<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Services\DocumentBrandingService;
use App\Services\Inventory\StockAvailabilityService;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockAvailability extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Interventions';

    protected static ?string $navigationLabel = 'Stock Availability';

    protected string $view = 'filament.pages.stock-availability';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'super_admin']) || auth()->user()?->can('view_welfare_interventions'), 403);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Export Stock Report (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(function () {
                    $service = app(StockAvailabilityService::class);
                    $metrics = $service->getItemStockMetrics();
                    $branding = app(DocumentBrandingService::class);

                    $pdfHtml = '
                    <style>
                        body { font-family: sans-serif; font-size: 12px; }
                        h2 { color: #1e3a8a; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
                        th { background-color: #f1f5f9; }
                        .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 10px; }
                        .badge-in { background-color: #dcfce7; color: #166534; }
                        .badge-low { background-color: #fef9c3; color: #854d0e; }
                        .badge-out { background-color: #fee2e2; color: #991b1b; }
                    </style>
                    <h2>GOF MIS — Stock Availability Report</h2>
                    <p>Generated on: '.now()->format('F d, Y H:i:s').'</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>On Hand</th>
                                <th>Reserved</th>
                                <th>Available</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';

                    foreach ($metrics as $row) {
                        $badgeClass = match ($row['status']) {
                            'IN_STOCK' => 'badge-in',
                            'LOW_STOCK' => 'badge-low',
                            default => 'badge-out',
                        };

                        $pdfHtml .= "
                            <tr>
                                <td>{$row['name']}</td>
                                <td>{$row['category_name']}</td>
                                <td>{$row['on_hand']}</td>
                                <td>{$row['reserved']}</td>
                                <td>{$row['available']}</td>
                                <td><span class='badge {$badgeClass}'>{$row['status']}</span></td>
                            </tr>";
                    }

                    $pdfHtml .= '</tbody></table>';

                    return response()->streamDownload(function () use ($pdfHtml) {
                        echo $pdfHtml;
                    }, 'stock-availability-report-'.now()->format('Y-m-d').'.html');
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Item::query()->with('category'))
            ->columns([
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('on_hand')
                    ->label('On Hand')
                    ->state(function (Item $record) {
                        $service = app(StockAvailabilityService::class);

                        return $service->getItemStockMetrics($record->id)->first()['on_hand'] ?? 0;
                    }),

                TextColumn::make('reserved')
                    ->label('Reserved')
                    ->state(function (Item $record) {
                        $service = app(StockAvailabilityService::class);

                        return $service->getItemStockMetrics($record->id)->first()['reserved'] ?? 0;
                    }),

                TextColumn::make('available')
                    ->label('Available Stock')
                    ->weight('bold')
                    ->state(function (Item $record) {
                        $service = app(StockAvailabilityService::class);

                        return $service->getItemStockMetrics($record->id)->first()['available'] ?? 0;
                    }),

                TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->color(function (Item $record) {
                        $service = app(StockAvailabilityService::class);
                        $status = $service->getItemStockMetrics($record->id)->first()['status'] ?? 'OUT_OF_STOCK';

                        return match ($status) {
                            'IN_STOCK' => 'success',
                            'LOW_STOCK' => 'warning',
                            default => 'danger',
                        };
                    })
                    ->state(function (Item $record) {
                        $service = app(StockAvailabilityService::class);

                        return $service->getItemStockMetrics($record->id)->first()['status'] ?? 'OUT_OF_STOCK';
                    }),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
            ]);
    }
}
