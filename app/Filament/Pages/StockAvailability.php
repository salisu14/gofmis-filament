<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Services\Inventory\StockAvailabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
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

                    $totalOnHand = $metrics->sum('on_hand');
                    $totalReserved = $metrics->sum('reserved');
                    $totalAvailable = $metrics->sum('available');

                    $pdfHtml = '<!DOCTYPE html>
                    <html>
                    <head>
                    <meta charset="utf-8">
                    <style>
                        body { font-family: sans-serif; font-size: 11px; color: #1e293b; margin: 30px; }
                        h2 { color: #1e3a8a; margin-bottom: 4px; }
                        .subtitle { color: #64748b; margin-bottom: 16px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
                        th { background-color: #f1f5f9; font-weight: 600; }
                        .text-right { text-align: right; }
                        .badge { padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 9px; }
                        .badge-in { background-color: #dcfce7; color: #166534; }
                        .badge-low { background-color: #fef9c3; color: #854d0e; }
                        .badge-out { background-color: #fee2e2; color: #991b1b; }
                        .summary { margin-top: 16px; font-size: 10px; color: #64748b; }
                        tfoot td { font-weight: bold; background-color: #f8fafc; }
                    </style>
                    </head>
                    <body>
                    <h2>GOF MIS &mdash; Stock Availability Report</h2>
                    <p class="subtitle">Generated on: '.now()->format('F d, Y H:i:s').'</p>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th class="text-right">On Hand</th>
                                <th class="text-right">Reserved</th>
                                <th class="text-right">Available</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';

                    $rowNum = 0;
                    foreach ($metrics as $row) {
                        $rowNum++;
                        $badgeClass = match ($row['status']) {
                            'IN_STOCK' => 'badge-in',
                            'LOW_STOCK' => 'badge-low',
                            default => 'badge-out',
                        };
                        $statusLabel = str_replace('_', ' ', $row['status']);
                        $unit = $row['unit_of_measure'] ?? 'Units';

                        $pdfHtml .= "
                            <tr>
                                <td>{$rowNum}</td>
                                <td>{$row['name']}</td>
                                <td>{$row['category_name']}</td>
                                <td>{$unit}</td>
                                <td class='text-right'>".number_format($row['on_hand'])."</td>
                                <td class='text-right'>".number_format($row['reserved'])."</td>
                                <td class='text-right'>".number_format($row['available'])."</td>
                                <td><span class='badge {$badgeClass}'>{$statusLabel}</span></td>
                            </tr>";
                    }

                    $pdfHtml .= "
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan='4'>Totals</td>
                                <td class='text-right'>".number_format($totalOnHand)."</td>
                                <td class='text-right'>".number_format($totalReserved)."</td>
                                <td class='text-right'>".number_format($totalAvailable)."</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class='summary'>Report generated by GOF MIS Inventory Module. Stock levels are derived from the canonical stock movements ledger.</p>
                    </body></html>";

                    $pdf = Pdf::loadHTML($pdfHtml)->setPaper('a4', 'landscape');
                    $filename = 'stock-availability-report-'.now()->format('Y-m-d').'.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
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

                TextColumn::make('unit_of_measure')
                    ->label('Unit')
                    ->default('Units'),

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
