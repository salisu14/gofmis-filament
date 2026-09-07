<?php

namespace App\Filament\Pages;

use App\Enums\AcademicProgressionDecision;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class EducationAnalytics extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Education';

    protected static ?string $navigationLabel = 'Education Analytics';

    protected string $view = 'filament.pages.education-analytics';

    protected static ?int $navigationSort = 10;

    public ?array $filterData = [];

    public string $activeTab = 'summary';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('orphan_education.analytics.view') ?? false;
    }

    public function mount(): void
    {
        $this->filterForm->fill([
            'status' => 'all',
        ]);
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    protected function getForms(): array
    {
        return [
            'filterForm',
        ];
    }

    public function filterForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('academic_session')
                    ->label('Academic Session')
                    ->options(fn () => OrphanEducation::query()->whereNotNull('academic_session')->pluck('academic_session', 'academic_session')->unique())
                    ->placeholder('All Sessions')
                    ->searchable()
                    ->live(),

                DatePicker::make('date_from')
                    ->label('Effective From')
                    ->live(),

                DatePicker::make('date_to')
                    ->label('Effective To')
                    ->live(),

                Select::make('institution_id')
                    ->label('School / Institution')
                    ->options(fn () => Institution::pluck('name', 'id'))
                    ->placeholder('All Institutions')
                    ->searchable()
                    ->live(),

                Select::make('orphan_class_id')
                    ->label('Class / Level')
                    ->options(fn () => OrphanClass::pluck('name', 'id'))
                    ->placeholder('All Classes')
                    ->searchable()
                    ->live(),

                Select::make('institution_type')
                    ->label('Education Type')
                    ->options([
                        'western' => 'Western',
                        'islamiyya' => 'Islamiyya',
                        'vocational' => 'Vocational',
                    ])
                    ->placeholder('All Types')
                    ->live(),

                Select::make('progression_decision')
                    ->label('Progression Decision')
                    ->options(collect(AcademicProgressionDecision::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                    ->placeholder('All Decisions')
                    ->live(),

                Select::make('zone_id')
                    ->label('Zone')
                    ->options(fn () => Zone::pluck('name', 'id'))
                    ->placeholder('All Zones')
                    ->searchable()
                    ->live(),

                Select::make('orphan_id')
                    ->label('Student / Orphan')
                    ->options(fn () => Orphan::pluck('full_name', 'id'))
                    ->placeholder('All Students')
                    ->searchable()
                    ->live(),

                Select::make('gender')
                    ->label('Gender')
                    ->options([
                        'MALE' => 'Male',
                        'FEMALE' => 'Female',
                    ])
                    ->placeholder('All Genders')
                    ->live(),

                Select::make('status')
                    ->label('Enrollment Status')
                    ->options([
                        'all' => 'All Enrollments',
                        'current' => 'Current Active Only',
                        'historical' => 'Historical Only',
                    ])
                    ->default('all')
                    ->live(),
            ])
            ->columns(4)
            ->statePath('filterData');
    }

    public static function buildFilteredQuery(array $filters = []): Builder
    {
        $query = OrphanEducation::query()
            ->with(['orphan.deceased.zone', 'orphanClass', 'institution', 'recordedBy']);

        if (! empty($filters['academic_session'])) {
            $query->where('academic_session', $filters['academic_session']);
        }

        if (! empty($filters['date_from'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('started_at', '>=', $filters['date_from'])
                    ->orWhere('ended_at', '>=', $filters['date_from']);
            });
        }

        if (! empty($filters['date_to'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('started_at', '<=', $filters['date_to'])
                    ->orWhere('ended_at', '<=', $filters['date_to']);
            });
        }

        if (! empty($filters['institution_id'])) {
            $query->where('institution_id', $filters['institution_id']);
        }

        if (! empty($filters['orphan_class_id'])) {
            $query->where('orphan_class_id', $filters['orphan_class_id']);
        }

        if (! empty($filters['institution_type'])) {
            $query->whereHas('institution', fn ($q) => $q->where('type', $filters['institution_type']));
        }

        if (! empty($filters['progression_decision'])) {
            $query->where('progression_decision', $filters['progression_decision']);
        }

        if (! empty($filters['zone_id'])) {
            $query->whereHas('orphan.deceased', fn ($q) => $q->where('zone_id', $filters['zone_id']));
        }

        if (! empty($filters['orphan_id'])) {
            $query->where('orphan_id', $filters['orphan_id']);
        }

        if (! empty($filters['gender'])) {
            $query->whereHas('orphan', fn ($q) => $q->where('gender', $filters['gender']));
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'current') {
                $query->where('is_current', true);
            } elseif ($filters['status'] === 'historical') {
                $query->where('is_current', false);
            }
        }

        return $query;
    }

    protected function getFilteredQuery(): Builder
    {
        $filters = $this->filterForm->getState();

        return static::buildFilteredQuery($filters);
    }

    public function getKpiStats(): array
    {
        $baseQuery = $this->getFilteredQuery();

        $currentCount = (clone $baseQuery)->where('is_current', true)->count();
        $promotionsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::PROMOTED)->count();
        $repetitionsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::REPEATED)->count();
        $demotionsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::DEMOTED)->count();
        $graduationsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::GRADUATED)->count();
        $transfersCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::TRANSFERRED)->count();
        $withdrawalsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::WITHDRAWN)->count();
        $dropoutsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::DROPPED_OUT)->count();

        $p6TransitionsCount = (clone $baseQuery)
            ->whereHas('orphanClass', fn ($q) => $q->where('name', 'LIKE', '%Primary 6%'))
            ->where('progression_decision', AcademicProgressionDecision::PROMOTED)
            ->whereHas('successorEnrollment.orphanClass', fn ($q) => $q->where('name', 'LIKE', '%JSS%1%')->orWhere('name', 'LIKE', '%JSS%I%'))
            ->count();

        $totalSupportCost = (float) (clone $baseQuery)->where('is_fee_supported', true)->sum('support_amount');
        $distinctOrphansCount = (clone $baseQuery)->distinct('orphan_id')->count('orphan_id');
        $avgSupportCost = $distinctOrphansCount > 0 ? $totalSupportCost / $distinctOrphansCount : 0.0;

        return [
            'current' => $currentCount,
            'promotions' => $promotionsCount,
            'repetitions' => $repetitionsCount,
            'demotions' => $demotionsCount,
            'graduations' => $graduationsCount,
            'transfers' => $transfersCount,
            'withdrawals' => $withdrawalsCount,
            'dropouts' => $dropoutsCount,
            'p6_transitions' => $p6TransitionsCount,
            'total_cost' => $totalSupportCost,
            'avg_cost' => $avgSupportCost,
        ];
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'repeated' => $this->getRepeatedTable($table),
            'graduation' => $this->getGraduationTable($table),
            'transition' => $this->getTransitionTable($table),
            'institution' => $this->getInstitutionTable($table),
            'lifetime_cost' => $this->getLifetimeCostTable($table),
            default => $this->getSummaryTable($table),
        };
    }

    protected function getSummaryTable(Table $table): Table
    {
        return $table
            ->query($this->getFilteredQuery())
            ->columns([
                TextColumn::make('orphan.full_name')->label('Student')->searchable()->sortable(),
                TextColumn::make('orphan.reg_no')->label('Reg No')->searchable(),
                TextColumn::make('institution.name')->label('Institution')->searchable(),
                TextColumn::make('orphanClass.name')->label('Class / Grade'),
                TextColumn::make('academic_session')->label('Session')->default('N/A'),
                TextColumn::make('progression_decision')
                    ->label('Decision / Outcome')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AcademicProgressionDecision ? $state->label() : (string) $state)
                    ->color(fn ($state) => $state instanceof AcademicProgressionDecision ? $state->color() : 'gray'),
                TextColumn::make('started_at')->label('Started')->date(),
                TextColumn::make('ended_at')
                    ->label('Ended')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d') : 'Active'),
                TextColumn::make('orphan.deceased.zone.name')->label('Zone'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getRepeatedTable(Table $table): Table
    {
        $query = $this->getFilteredQuery()
            ->where('progression_decision', AcademicProgressionDecision::REPEATED);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('orphan.full_name')->label('Student')->searchable(),
                TextColumn::make('orphan.reg_no')->label('Reg No'),
                TextColumn::make('institution.name')->label('Institution'),
                TextColumn::make('orphanClass.name')->label('Repeated Class'),
                TextColumn::make('academic_session')->label('Session'),
                TextColumn::make('ended_at')->label('Decision Date')->date(),
                TextColumn::make('progression_reason')->label('Reason')->wrap(),
                TextColumn::make('orphan.deceased.zone.name')->label('Zone'),
            ]);
    }

    protected function getGraduationTable(Table $table): Table
    {
        $query = $this->getFilteredQuery()
            ->where('progression_decision', AcademicProgressionDecision::GRADUATED);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('orphan.full_name')->label('Student')->searchable(),
                TextColumn::make('orphan.reg_no')->label('Reg No'),
                TextColumn::make('institution.name')->label('Institution'),
                TextColumn::make('orphanClass.name')->label('Final Class'),
                TextColumn::make('academic_session')->label('Graduation Session'),
                TextColumn::make('ended_at')->label('Graduation Date')->date(),
                TextColumn::make('orphan.deceased.zone.name')->label('Zone'),
            ]);
    }

    protected function getTransitionTable(Table $table): Table
    {
        // P6 -> JSS1 transition verified via direct lineage reference or P6 promoted with successor JSS1
        $query = $this->getFilteredQuery()
            ->where('progression_decision', AcademicProgressionDecision::PROMOTED)
            ->whereHas('orphanClass', fn ($q) => $q->where('name', 'Primary 6'))
            ->whereHas('successorEnrollment.orphanClass', fn ($q) => $q->where('name', 'like', '%JSS%'));

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('orphan.full_name')->label('Student')->searchable(),
                TextColumn::make('orphan.reg_no')->label('Reg No'),
                TextColumn::make('institution.name')->label('Primary School'),
                TextColumn::make('successorEnrollment.institution.name')->label('Secondary School')->default('Same Institution'),
                TextColumn::make('academic_session')->label('P6 Session'),
                TextColumn::make('successorEnrollment.academic_session')->label('JSS I Session'),
                TextColumn::make('ended_at')->label('Transition Date')->date(),
                TextColumn::make('orphan.deceased.zone.name')->label('Zone'),
            ]);
    }

    protected function getInstitutionTable(Table $table): Table
    {
        // Query aggregating institution performance
        return $table
            ->query($this->getFilteredQuery())
            ->columns([
                TextColumn::make('institution.name')->label('Institution')->searchable(),
                TextColumn::make('orphanClass.name')->label('Class'),
                TextColumn::make('academic_session')->label('Session'),
                TextColumn::make('progression_decision')
                    ->label('Outcome')
                    ->badge(),
            ]);
    }

    protected function getLifetimeCostTable(Table $table): Table
    {
        return $table
            ->query($this->getFilteredQuery())
            ->columns([
                TextColumn::make('orphan.full_name')->label('Student')->searchable(),
                TextColumn::make('orphan.reg_no')->label('Reg No'),
                TextColumn::make('institution.name')->label('Current/Latest School'),
                TextColumn::make('orphanClass.name')->label('Class'),
                TextColumn::make('support_amount')->label('Support Fee')->money('NGN'),
                TextColumn::make('started_at')->label('Enrolled Date')->date(),
            ]);
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('orphan_education.analytics.export'), 403, 'Unauthorized to export education analytics.');

        $records = $this->getFilteredQuery()->get();

        $response = new StreamedResponse(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student Name',
                'Reg No',
                'Institution',
                'Class',
                'Session',
                'Decision',
                'Reason',
                'Started At',
                'Ended At',
                'Support Amount',
                'Zone',
            ]);

            foreach ($records as $row) {
                fputcsv($handle, [
                    $row->orphan?->full_name ?? '',
                    $row->orphan?->reg_no ?? '',
                    $row->institution?->name ?? '',
                    $row->orphanClass?->name ?? $row->class_level ?? '',
                    $row->academic_session ?? 'N/A',
                    $row->progression_decision?->label() ?? 'Current Active',
                    $row->progression_reason ?? '',
                    $row->started_at?->toDateString() ?? '',
                    $row->ended_at?->toDateString() ?? 'Active',
                    $row->support_amount ?? '0.00',
                    $row->orphan?->deceased?->zone?->name ?? '',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="education_analytics_export_'.now()->format('Ymd_His').'.csv"');

        return $response;
    }
}
