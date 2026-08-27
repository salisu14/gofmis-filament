<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\ViewProject;
use App\Models\Deceased;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
});

test('1. end-to-end project proposal -> admin approval -> milestone/expense tracking -> completion -> coordinator visibility', function () {
    // 1. Coordinator creates Project proposal in PLANNING status
    $project = Project::create([
        'name' => 'Solar Water Borehole Installation',
        'type' => ProjectType::WATER,
        'status' => ProjectStatus::PLANNING,
        'zone_id' => $this->zone->id,
        'deceased_id' => $this->deceased->id,
        'budget_allocated' => 750000.00,
        'location_address' => 'Kano Central District',
    ]);

    expect($project->status)->toBe(ProjectStatus::PLANNING);

    // 2. Admin Approves Project
    $project->update(['status' => ProjectStatus::APPROVED]);
    expect($project->fresh()->status)->toBe(ProjectStatus::APPROVED);

    // 3. Project moves to IN_PROGRESS with Milestone and Expense tracking
    $project->update([
        'status' => ProjectStatus::IN_PROGRESS,
        'progress_percentage' => 50,
    ]);

    $milestone = ProjectMilestone::create([
        'project_id' => $project->id,
        'title' => 'Drilling & Well Construction',
        'status' => 'completed',
        'due_date' => now()->addDays(10),
        'completed_date' => now()->toDateString(),
    ]);

    $expense = ProjectExpense::create([
        'project_id' => $project->id,
        'category' => 'equipment',
        'description' => 'Borehole Drilling Machinery Rental',
        'amount' => 350000.00,
        'expense_date' => now()->toDateString(),
        'recorded_by' => $this->admin->id,
    ]);

    expect($milestone->status)->toBe('completed');
    expect((float) $expense->amount)->toEqual(350000.00);
    expect($project->fresh()->status)->toBe(ProjectStatus::IN_PROGRESS);

    // 4. Project Completion
    $project->update([
        'status' => ProjectStatus::COMPLETED,
        'progress_percentage' => 100,
    ]);
    expect($project->fresh()->status)->toBe(ProjectStatus::COMPLETED);

    // 5. Coordinator Outcome Visibility
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Solar Water Borehole Installation');
});

test('2. admin project table approve action executes without using $this in static closure context error', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $project = Project::create([
        'name' => 'Community School Renovation',
        'type' => ProjectType::SCHOOL,
        'status' => ProjectStatus::PLANNING,
        'zone_id' => $this->zone->id,
        'deceased_id' => $this->deceased->id,
        'budget_allocated' => 1200000.00,
    ]);

    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
        ->assertSuccessful()
        ->assertTableActionVisible('approve', $project)
        ->callTableAction('approve', $project)
        ->assertHasNoTableActionErrors();

    $project->refresh();
    expect($project->status)->toBe(ProjectStatus::APPROVED);
    expect($project->milestones()->count())->toBeGreaterThan(0);

    // Approve action is no longer visible once approved, start action is visible
    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
        ->assertTableActionHidden('approve', $project)
        ->assertTableActionVisible('start', $project);
});

test('3. admin project table start, hold, resume, complete actions execute cleanly without errors', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $project = Project::create([
        'name' => 'Health Clinic Construction',
        'type' => ProjectType::CLINIC,
        'status' => ProjectStatus::APPROVED,
        'zone_id' => $this->zone->id,
        'deceased_id' => $this->deceased->id,
        'budget_allocated' => 2000000.00,
    ]);

    // 1. Start Work
    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
        ->callTableAction('start', $project)
        ->assertHasNoTableActionErrors();

    expect($project->fresh()->status)->toBe(ProjectStatus::IN_PROGRESS);

    // 2. Place on Hold
    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
        ->callTableAction('hold', $project, ['reason' => 'Awaiting weather clearance'])
        ->assertHasNoTableActionErrors();

    expect($project->fresh()->status)->toBe(ProjectStatus::ON_HOLD);
    expect($project->fresh()->notes)->toContain('Awaiting weather clearance');

    // 3. Resume Project
    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
        ->callTableAction('resume', $project)
        ->assertHasNoTableActionErrors();

    expect($project->fresh()->status)->toBe(ProjectStatus::IN_PROGRESS);

    // Mark all milestones completed so complete Project succeeds
    $project->milestones()->update(['status' => 'completed']);

    // 4. Mark Complete
    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
        ->callTableAction('complete', $project)
        ->assertHasNoTableActionErrors();

    expect($project->fresh()->status)->toBe(ProjectStatus::COMPLETED);
});
