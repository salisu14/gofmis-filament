<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\ViewProject;
use App\Models\Deceased;
use App\Models\Project;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'South Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    $this->project = Project::create([
        'name' => 'Borehole Installation Project',
        'type' => ProjectType::WATER,
        'status' => ProjectStatus::PLANNING,
        'zone_id' => $this->zone->id,
        'deceased_id' => $this->deceased->id,
        'budget_allocated' => 500000,
        'location_address' => 'Kano North Community',
    ]);

    $this->actingAs($this->coordinator);
});

test('1. coordinator project pages render cleanly', function () {
    Livewire::test(ListProjects::class)
        ->assertSuccessful();

    Livewire::test(CreateProject::class)
        ->assertSuccessful();

    Livewire::test(ViewProject::class, ['record' => $this->project->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->assertSuccessful();
});

test('2. coordinator can create project for own zone beneficiary', function () {
    Livewire::test(CreateProject::class)
        ->set('data.name', 'Housing Renovation Project')
        ->set('data.type', ProjectType::HOUSE->value)
        ->set('data.deceased_id', (string) $this->deceased->id)
        ->set('data.budget_allocated', 250000)
        ->set('data.description', 'Roof repair and wall plastering')
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('projects', [
        'name' => 'Housing Renovation Project',
        'zone_id' => $this->zone->id,
        'deceased_id' => $this->deceased->id,
        'budget_allocated' => 250000.00,
    ]);
});

test('3. coordinator cannot select other-zone deceased for project creation', function () {
    Livewire::test(CreateProject::class)
        ->set('data.name', 'Cross Zone Project')
        ->set('data.type', ProjectType::HOUSE->value)
        ->set('data.deceased_id', (string) $this->otherDeceased->id)
        ->set('data.budget_allocated', 100000)
        ->call('create')
        ->assertHasFormErrors(['deceased_id']);
});
