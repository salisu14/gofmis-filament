<?php

namespace App\Filament\Pages\Reports;

use App\Enums\ProjectStatus;
use App\Models\Project;
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

class ProjectReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Project Report';

    protected static ?string $title = 'Project Report';

    protected static ?string $slug = 'reports/project-report';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';

    protected string $view = 'filament.pages.reports.project-report';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isAdmin() || $user->isSuperAdmin() || $user->can('view_projects'));
    }

    public function mount(): void
    {
        if (! static::canAccess()) {
            abort(403, 'Unauthorized access to project reports.');
        }

        $this->form->fill([
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->toDateString(),
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

                Select::make('status')
                    ->label('Project Status')
                    ->options(ProjectStatus::class)
                    ->placeholder('All Statuses')
                    ->live(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getReportQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Project Name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->placeholder('Global'),

                TextColumn::make('budget_allocated')
                    ->label('Budget')
                    ->money('NGN'),

                TextColumn::make('budget_spent')
                    ->label('Spent')
                    ->money('NGN'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function getReportQuery(): Builder
    {
        $startDate = $this->data['start_date'] ?? now()->startOfYear()->toDateString();
        $endDate = $this->data['end_date'] ?? now()->toDateString();
        $status = $this->data['status'] ?? null;

        $query = Project::query()
            ->with(['zone', 'coordinator'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function getSummaryMetrics(): array
    {
        $query = $this->getReportQuery();
        $projects = $query->get();

        return [
            'total_projects' => $projects->count(),
            'completed_projects' => $projects->where('status', ProjectStatus::COMPLETED)->count(),
            'active_projects' => $projects->where('status', ProjectStatus::IN_PROGRESS)->count(),
            'total_budget' => (float) $projects->sum('budget_allocated'),
            'total_spent' => (float) $projects->sum('budget_spent'),
        ];
    }
}
