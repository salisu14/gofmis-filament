<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Widgets\ProjectReportWidget;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'Zone Central']);
});

function createKpiProject(string $name, float $allocated): Project
{
    return Project::create([
        'name' => $name,
        'type' => ProjectType::SCHOOL,
        'status' => ProjectStatus::IN_PROGRESS,
        'budget_allocated' => $allocated,
        'zone_id' => Zone::first()->id,
        'start_date' => now()->toDateString(),
    ]);
}

it('ensures KPI widget, project rows, and calculations derive spent from authoritative expenses', function () {
    // Project 1: Allocated 500,000, Spent 2,120,000 (Overspent)
    $p1 = createKpiProject('Project Alpha', 500000);
    ProjectExpense::create([
        'project_id' => $p1->id,
        'category' => 'labor',
        'description' => 'Labour payment',
        'amount' => 2120000,
        'expense_date' => now()->toDateString(),
        'recorded_by' => $this->admin->id,
    ]);

    // Project 2: Allocated 1,000,000, Spent 40,000
    $p2 = createKpiProject('Project Beta', 1000000);
    ProjectExpense::create([
        'project_id' => $p2->id,
        'category' => 'materials',
        'description' => 'Bricks',
        'amount' => 20000,
        'expense_date' => now()->toDateString(),
        'recorded_by' => $this->admin->id,
    ]);
    ProjectExpense::create([
        'project_id' => $p2->id,
        'category' => 'materials',
        'description' => 'Sand',
        'amount' => 20000,
        'expense_date' => now()->toDateString(),
        'recorded_by' => $this->admin->id,
    ]);

    // Individual project row calculations
    expect($p1->fresh()->budget_spent)->toBe(2120000.0);
    expect($p1->fresh()->budget_remaining)->toBe(-1620000.0); // 500,000 - 2,120,000 = -1,620,000 (Overspent)
    expect($p1->fresh()->is_over_budget)->toBeTrue();

    expect($p2->fresh()->budget_spent)->toBe(40000.0);
    expect($p2->fresh()->budget_remaining)->toBe(960000.0);
    expect($p2->fresh()->is_over_budget)->toBeFalse();

    // Portfolio aggregates
    $totalAllocated = Project::sum('budget_allocated');
    $allProjects = Project::get();
    $totalSpent = (float) ProjectExpense::whereIn('project_id', $allProjects->pluck('id'))->sum('amount');
    $portfolioRemaining = $totalAllocated - $totalSpent;

    expect((float) $totalAllocated)->toBe(1500000.0);
    expect($totalSpent)->toBe(2160000.0);
    expect($portfolioRemaining)->toBe(-660000.0);

    // KPI Widget renders the exact authoritative totals
    Livewire::actingAs($this->admin)
        ->test(ProjectReportWidget::class)
        ->assertSee('Total Budget Allocated')
        ->assertSee('₦1,500,000.00')
        ->assertSee('Total Budget Spent')
        ->assertSee('₦2,160,000.00');
});

it('supports negative remaining balance when overspent without clamping to zero', function () {
    $project = createKpiProject('Overspent Project', 100000);

    ProjectExpense::create([
        'project_id' => $project->id,
        'category' => 'other',
        'description' => 'Unexpected structural repair',
        'amount' => 250000,
        'expense_date' => now()->toDateString(),
        'recorded_by' => $this->admin->id,
    ]);

    expect($project->fresh()->budget_spent)->toBe(250000.0);
    expect($project->fresh()->budget_remaining)->toBe(-150000.0);
    expect($project->fresh()->is_over_budget)->toBeTrue();
});
