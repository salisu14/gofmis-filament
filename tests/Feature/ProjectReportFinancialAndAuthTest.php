<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\UserStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

function makeProject(int $budget = 500000): Project
{
    $user = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE]);
    $user->assignRole('admin');

    return Project::create([
        'name' => 'Well Project Alpha',
        'type' => ProjectType::WATER,
        'status' => ProjectStatus::IN_PROGRESS,
        'budget_allocated' => $budget,
        'zone_id' => Zone::create(['name' => 'Zone P'])->id,
        'coordinator_id' => $user->id,
        'start_date' => now()->subMonths(2)->toDateString(),
        'expected_completion_date' => now()->addMonths(2)->toDateString(),
    ]);
}

test('project budget spent and remaining derive from authoritative expenses', function () {
    $project = makeProject(500000);

    $project->expenses()->create(['category' => 'materials', 'description' => 'A', 'amount' => 100000, 'expense_date' => now()->toDateString(), 'recorded_by' => $project->coordinator_id]);
    $project->expenses()->create(['category' => 'labour', 'description' => 'B', 'amount' => 75000, 'expense_date' => now()->toDateString(), 'recorded_by' => $project->coordinator_id]);
    $project->expenses()->create(['category' => 'other', 'description' => 'C', 'amount' => 25000, 'expense_date' => now()->toDateString(), 'recorded_by' => $project->coordinator_id]);

    $project->refresh();

    expect((float) $project->budget_spent)->toBe(200000.0)
        ->and((float) $project->budget_remaining)->toBe(300000.0)
        ->and($project->is_over_budget)->toBeFalse();
});

test('report query derives the same budget spent from expenses', function () {
    $project = makeProject(500000);
    $project->expenses()->create(['category' => 'materials', 'description' => 'A', 'amount' => 100000, 'expense_date' => now()->toDateString(), 'recorded_by' => $project->coordinator_id]);
    $project->expenses()->create(['category' => 'labour', 'description' => 'B', 'amount' => 75000, 'expense_date' => now()->toDateString(), 'recorded_by' => $project->coordinator_id]);
    $project->expenses()->create(['category' => 'other', 'description' => 'C', 'amount' => 25000, 'expense_date' => now()->toDateString(), 'recorded_by' => $project->coordinator_id]);

    // Mirror the ProjectReport page/controller query.
    $projects = Project::query()->with(['zone', 'coordinator'])->get();
    $totalSpent = (float) $projects->sum('budget_spent');
    $totalBudget = (float) $projects->sum('budget_allocated');

    expect($totalBudget)->toBe(500000.0)
        ->and($totalSpent)->toBe(200000.0)
        ->and($totalBudget - $totalSpent)->toBe(300000.0);
});

test('no writable budget_spent database source exists', function () {
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('projects');

    expect($columns)->not->toContain('budget_spent');
});

test('unauthorized user cannot preview or download the project report PDF', function () {
    $user = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE]);

    $this->actingAs($user)->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $user->id]);

    $this->get(route('reports.project-report.pdf', ['action' => 'preview']))->assertForbidden();
    $this->get(route('reports.project-report.pdf'))->assertForbidden();
});

test('permitted non-super-admin with view_projects can download the project report PDF', function () {
    $user = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE]);
    $user->assignRole('coordinator');
    $user->givePermissionTo('view_projects');

    $this->actingAs($user)->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $user->id]);

    $this->get(route('reports.project-report.pdf'))->assertOk();
});
