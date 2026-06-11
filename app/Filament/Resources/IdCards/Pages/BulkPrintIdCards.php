<?php

// app/Filament/Resources/IdCardResource/Pages/BulkPrintIdCards.php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Jobs\GenerateIdCardsJob;
use App\Models\IdCardPrintBatch;
use App\Models\IdCardTemplate;
use App\Models\Orphan;
use App\Models\Widow;
use App\Models\Zone;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class BulkPrintIdCards extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = IdCardResource::class;

    protected static ?string $title = 'Bulk ID Card Print';

    public ?array $data = [];

    public int $estimatedCount = 0;

    public bool $isCalculating = false;

    public ?IdCardPrintBatch $currentBatch = null;

    protected string $view = 'filament.resources.id-cards.pages.bulk-print-id-cards';

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'widow',
            'range_type' => 'all',
            'template_id' => IdCardTemplate::query()->active()->forType('widow')->latest('updated_at')->value('id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Print Configuration')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Beneficiary Type')
                            ->options([
                                'widow' => 'Widows',
                                'orphan' => 'Orphans',
                                'mixed' => 'Mixed (Both)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state) {
                                $this->resetCount();
                                $this->data['template_id'] = $state && $state !== 'mixed'
                                    ? IdCardTemplate::query()->active()->forType($state)->latest('updated_at')->value('id')
                                    : null;
                            }),

                        Forms\Components\Select::make('template_id')
                            ->label('Card Template')
                            ->options(fn (Get $get) => $get('type') && $get('type') !== 'mixed'
                                ? IdCardTemplate::query()->active()->forType($get('type'))->pluck('name', 'id')
                                : [])
                            ->visible(fn (Get $get): bool => $get('type') !== 'mixed')
                            ->required(fn (Get $get): bool => $get('type') !== 'mixed')
                            ->helperText('Mixed batches use each beneficiary type’s latest active template.'),
                    ]),

                Section::make('Range Selection')
                    ->schema([
                        Forms\Components\Radio::make('range_type')
                            ->label('Select Range')
                            ->options([
                                'all' => 'All Eligible Beneficiaries',
                                'date' => 'Date Range (Registration)',
                                'reg_no' => 'Registration Number Range',
                                'specific' => 'Specific Beneficiaries',
                            ])
                            ->required()
                            ->live()
                            ->inline(),

                        Grid::make(2)
                            ->visible(fn (Get $get) => $get('range_type') === 'date')
                            ->schema([
                                DatePicker::make('range.start_date')
                                    ->label('From')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->resetCount()),
                                DatePicker::make('range.end_date')
                                    ->label('To')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->resetCount()),
                            ]),

                        Grid::make(2)
                            ->visible(fn (Get $get) => $get('range_type') === 'reg_no')
                            ->schema([
                                TextInput::make('range.start_reg_no')
                                    ->label('From Reg. No')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn () => $this->resetCount()),
                                TextInput::make('range.end_reg_no')
                                    ->label('To Reg. No')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn () => $this->resetCount()),
                            ]),

                        Select::make('range.specific_ids')
                            ->label('Select Beneficiaries')
                            ->visible(fn (Get $get) => $get('range_type') === 'specific')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Get $get) {
                                $type = $get('type');
                                if ($type === 'mixed') {
                                    return [];
                                }

                                $model = $type === 'widow' ? Widow::class : Orphan::class;

                                return $model::where('is_eligible', true)
                                    ->whereDoesntHave('idCards', fn ($q) => $q->where('status', 'active'))
                                    ->pluck('full_name', 'id');
                            })
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetCount()),
                    ]),

                Section::make('Additional Filters')
                    ->columns(3)
                    ->schema([
                        Select::make('filters.zone_id')
                            ->label('Zone')
                            ->options(Zone::pluck('name', 'id'))
                            ->placeholder('All Zones')
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetCount()),

                        Select::make('filters.gender')
                            ->label('Gender')
                            ->options([
                                'MALE' => 'Male',
                                'FEMALE' => 'Female',
                            ])
                            ->placeholder('All Genders')
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetCount()),

                        Toggle::make('filters.exclude_printed')
                            ->label('Exclude Already Printed')
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetCount()),
                    ]),

                Section::make('Batch Information')
                    ->schema([
                        TextInput::make('batch_name')
                            ->label('Batch Name')
                            ->required()
                            ->placeholder('e.g., Widows Q2 2026 Batch 1')
                            ->default('Batch '.now()->format('Y-m-d H:i')),

                        Placeholder::make('estimated_count')
                            ->label('Estimated Cards')
                            ->content(fn (): string => $this->isCalculating
                                ? 'Calculating...'
                                : ($this->estimatedCount > 0
                                    ? number_format($this->estimatedCount).' cards'
                                    : 'Click "Calculate Count" to preview')
                            ),

                        Actions::make([
                            Action::make('calculate')
                                ->label('Calculate Count')
                                ->icon('heroicon-o-calculator')
                                ->color('info')
                                ->action(fn () => $this->calculateCount()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function resetCount(): void
    {
        $this->estimatedCount = 0;
    }

    public function calculateCount(): void
    {
        $this->isCalculating = true;

        try {
            $data = $this->form->getState();
            $this->estimatedCount = $data['type'] === 'mixed'
                ? $this->applyFilters(Widow::query(), $data)->count()
                    + $this->applyFilters(Orphan::query(), $data)->count()
                : $this->buildQuery()->count();

            Notification::make()
                ->title('Count Calculated')
                ->body("Found {$this->estimatedCount} eligible beneficiaries")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->isCalculating = false;
    }

    private function buildQuery()
    {
        $data = $this->form->getState();
        $type = $data['type'];

        if ($type === 'mixed') {
            throw new \LogicException('Mixed batches must be loaded as separate widow and orphan collections.');
        }

        $model = $type === 'widow' ? Widow::class : Orphan::class;

        return $this->applyFilters($model::query(), $data);
    }

    private function applyFilters($query, array $data)
    {
        // Base eligibility filter
        $query->where('is_eligible', true);

        // Exclude already printed
        if (($data['filters']['exclude_printed'] ?? true)) {
            $query->whereDoesntHave('idCards', fn ($q) => $q->where('status', 'active'));
        }

        // Zone filter
        if (! empty($data['filters']['zone_id'])) {
            $query->whereHas('deceased.zone', fn ($q) => $q->where('id', $data['filters']['zone_id']));
        }

        // Gender filter
        if (! empty($data['filters']['gender']) && $query->getModel() instanceof Orphan) {
            $query->where('gender', $data['filters']['gender']);
        }

        // Range filters
        if ($data['range_type'] === 'date' && ! empty($data['range']['start_date']) && ! empty($data['range']['end_date'])) {
            $query->whereBetween('created_at', [$data['range']['start_date'], $data['range']['end_date']]);
        }

        if ($data['range_type'] === 'reg_no' && ! empty($data['range']['start_reg_no']) && ! empty($data['range']['end_reg_no'])) {
            $query->whereBetween('reg_no', [$data['range']['start_reg_no'], $data['range']['end_reg_no']]);
        }

        if ($data['range_type'] === 'specific' && ! empty($data['range']['specific_ids'])) {
            $query->whereIn('id', $data['range']['specific_ids']);
        }

        return $query;
    }

    public function downloadBatch(): void
    {
        if (! $this->currentBatch || $this->currentBatch->status !== 'completed') {
            Notification::make()
                ->title('Batch not ready')
                ->body('Please wait for the batch to complete processing.')
                ->warning()
                ->send();

            return;
        }

        $this->redirectRoute('filament.admin.resources.id-card-print-batches.view', [
            'record' => $this->currentBatch,
        ]);
    }

    public function refreshBatch(): void
    {
        if ($this->currentBatch) {
            $this->currentBatch->refresh();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('create_batch')
                ->label('Generate & Print Cards')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Confirm Bulk Print')
                ->modalDescription(fn (): string => "This will generate {$this->estimatedCount} ID cards. Continue?")
                ->modalSubmitActionLabel('Yes, Generate')
                ->action(fn () => $this->createBatch())
                ->disabled(fn (): bool => $this->estimatedCount === 0),
        ];
    }

    public function createBatch(): void
    {
        $data = $this->form->getState();

        if ($this->estimatedCount === 0) {
            Notification::make()
                ->title('No beneficiaries found')
                ->body('Please adjust your filters and try again.')
                ->warning()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($data) {
                $batch = IdCardPrintBatch::create([
                    'batch_name' => $data['batch_name'],
                    'type' => $data['type'],
                    'filters' => [
                        'zone_id' => $data['filters']['zone_id'] ?? null,
                        'gender' => $data['filters']['gender'] ?? null,
                        'template_id' => $data['template_id'] ?? null,
                    ],
                    'range' => $this->getRangePayload($data),
                    'total_count' => $this->estimatedCount,
                    'created_by' => auth()->id(),
                ]);

                // Get beneficiaries and dispatch job
                $beneficiaries = $this->buildBeneficiaryCollection($data);
                GenerateIdCardsJob::dispatch(
                    $batch,
                    $beneficiaries,
                    $data['type'] === 'mixed' ? null : ($data['template_id'] ?? null)
                );

                $this->currentBatch = $batch;

                Notification::make()
                    ->title('Batch Created Successfully')
                    ->body("Batch '{$batch->batch_name}' is now processing {$batch->total_count} cards.")
                    ->success()
                    ->send();
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Create Batch')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function getRangePayload(array $data): ?array
    {
        return match ($data['range_type']) {
            'date' => [
                'start_date' => $data['range']['start_date'] ?? null,
                'end_date' => $data['range']['end_date'] ?? null,
            ],
            'reg_no' => [
                'start_reg_no' => $data['range']['start_reg_no'] ?? null,
                'end_reg_no' => $data['range']['end_reg_no'] ?? null,
            ],
            'specific' => [
                'specific_ids' => $data['range']['specific_ids'] ?? [],
            ],
            default => null,
        };
    }

    private function buildBeneficiaryCollection(array $data): \Illuminate\Support\Collection
    {
        if ($data['type'] !== 'mixed') {
            return $this->buildQuery()->get();
        }

        return $this->applyFilters(Widow::query(), $data)
            ->get()
            ->merge($this->applyFilters(Orphan::query(), $data)->get());
    }
}
