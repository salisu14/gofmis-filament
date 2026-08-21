<?php

namespace App\Filament\Pages\Reports;

use App\Enums\PrescriptionStatus;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\Widow;
use App\Models\Zone;
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
use Illuminate\Database\Eloquent\Builder;

class PrescriptionReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Healthcare Reports';

    protected static ?string $title = 'Healthcare Prescription Period Report';

    protected static ?string $slug = 'reports/prescription-report';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.reports.prescription-report';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isAdmin() || $user->isSuperAdmin() || $user->can('view_medicals'));
    }

    public function mount(): void
    {
        if (! static::canAccess()) {
            abort(403, 'Unauthorized access to healthcare reports.');
        }

        $this->form->fill([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'patient_type' => null,
            'zone_id' => null,
            'status' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('start_date')
                    ->label('From Date')
                    ->required()
                    ->live(),

                DatePicker::make('end_date')
                    ->label('To Date')
                    ->required()
                    ->live(),

                Select::make('patient_type')
                    ->label('Patient Category')
                    ->options([
                        'orphan' => 'Orphan',
                        'widow' => 'Widow',
                    ])
                    ->placeholder('All Categories')
                    ->live(),

                Select::make('zone_id')
                    ->label('Zone')
                    ->options(Zone::pluck('name', 'id'))
                    ->placeholder('All Zones')
                    ->live(),

                Select::make('status')
                    ->label('Treatment Status')
                    ->options(PrescriptionStatus::class)
                    ->placeholder('All Statuses')
                    ->live(),
            ])
            ->columns(5)
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getReportQuery())
            ->columns([
                TextColumn::make('prescription_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('prescribable.full_name')
                    ->label('Patient Name')
                    ->searchable()
                    ->getStateUsing(fn (Prescription $record) => $record->prescribable?->full_name ?? 'N/A'),

                TextColumn::make('prescribable_type')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_contains($state, 'Widow') ? 'Widow' : 'Orphan')
                    ->color(fn (string $state) => str_contains($state, 'Widow') ? 'warning' : 'info'),

                TextColumn::make('prescribable.reg_no')
                    ->label('Reg No')
                    ->searchable(),

                TextColumn::make('prescribable.deceased.zone.name')
                    ->label('Zone')
                    ->placeholder('N/A'),

                TextColumn::make('illness_name')
                    ->label('Diagnosis')
                    ->searchable(),

                TextColumn::make('doctor_name')
                    ->label('Doctor / Hospital')
                    ->searchable(),

                TextColumn::make('lab_test_cost')
                    ->label('Lab Cost')
                    ->money('NGN'),

                TextColumn::make('drug_cost')
                    ->label('Drug Cost')
                    ->money('NGN'),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('NGN')
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('prescription_date', 'desc');
    }

    public function getReportQuery(): Builder
    {
        $startDate = $this->data['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $this->data['end_date'] ?? now()->toDateString();
        $patientType = $this->data['patient_type'] ?? null;
        $zoneId = $this->data['zone_id'] ?? null;
        $status = $this->data['status'] ?? null;

        $query = Prescription::query()
            ->with(['prescribable.deceased.zone', 'illnessModel', 'user'])
            ->whereBetween('prescription_date', [$startDate, $endDate]);

        if ($patientType === 'orphan') {
            $query->where('prescribable_type', Orphan::class);
        } elseif ($patientType === 'widow') {
            $query->where('prescribable_type', Widow::class);
        }

        if ($zoneId) {
            $query->whereHasMorph('prescribable', [Orphan::class, Widow::class], function ($q) use ($zoneId) {
                $q->whereHas('deceased', fn ($d) => $d->where('zone_id', $zoneId));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function getSummaryMetrics(): array
    {
        $query = $this->getReportQuery();
        $prescriptions = $query->get();

        return [
            'total_prescriptions' => $prescriptions->count(),
            'orphan_count' => $prescriptions->where('prescribable_type', Orphan::class)->count(),
            'widow_count' => $prescriptions->where('prescribable_type', Widow::class)->count(),
            'total_lab_cost' => (float) $prescriptions->sum('lab_test_cost'),
            'total_drug_cost' => (float) $prescriptions->sum('drug_cost'),
            'total_healthcare_cost' => (float) $prescriptions->sum(fn ($p) => $p->total_cost),
            'pending_count' => $prescriptions->reject(fn ($p) => $p->isTreated())->count(),
            'treated_count' => $prescriptions->filter(fn ($p) => $p->isTreated())->count(),
        ];
    }
}
