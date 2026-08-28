<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\ProjectExpenses\Pages\CreateProjectExpense;
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

    $this->zone = Zone::create(['name' => 'Zone North']);
});

function createTestProject(array $attributes = []): Project
{
    return Project::create(array_merge([
        'name' => 'School Renovation',
        'type' => ProjectType::SCHOOL,
        'status' => ProjectStatus::IN_PROGRESS,
        'budget_allocated' => 5000000,
        'zone_id' => Zone::first()->id,
        'start_date' => now()->toDateString(),
    ], $attributes));
}

it('creates exactly one ProjectExpense row on a single form submission', function () {
    $project = createTestProject(['budget_allocated' => 5000000]);

    expect(ProjectExpense::count())->toBe(0);
    expect($project->fresh()->budget_spent)->toBe(0.0);

    Livewire::actingAs($this->admin)
        ->test(CreateProjectExpense::class)
        ->fillForm([
            'project_id' => $project->id,
            'category' => 'labor',
            'description' => 'Site clearance labour payment',
            'amount' => 2120000,
            'expense_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProjectExpense::count())->toBe(1);

    $expense = ProjectExpense::first();
    expect((float) $expense->amount)->toBe(2120000.0);
    expect($expense->project_id)->toBe($project->id);

    // Derived project spent amount increases exactly once
    expect($project->fresh()->budget_spent)->toBe(2120000.0);
});

it('allows two intentional separate expense submissions with identical business values to exist', function () {
    $project = createTestProject(['budget_allocated' => 5000000]);

    // First submission: Materials NGN 20,000
    Livewire::actingAs($this->admin)
        ->test(CreateProjectExpense::class)
        ->fillForm([
            'project_id' => $project->id,
            'category' => 'materials',
            'description' => 'Cement bags',
            'amount' => 20000,
            'expense_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Second separate submission: Materials NGN 20,000
    Livewire::actingAs($this->admin)
        ->test(CreateProjectExpense::class)
        ->fillForm([
            'project_id' => $project->id,
            'category' => 'materials',
            'description' => 'Cement bags',
            'amount' => 20000,
            'expense_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProjectExpense::count())->toBe(2);

    $expenses = ProjectExpense::where('project_id', $project->id)->get();
    expect($expenses)->toHaveCount(2);

    // Total spent is 20,000 + 20,000 = 40,000
    expect($project->fresh()->budget_spent)->toBe(40000.0);
});
