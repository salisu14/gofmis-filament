<?php

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\OrphanStatus;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\CreateHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\EditHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ViewHealthcareRequest;
use App\Models\Deceased;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Widow;
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

    $this->zone = Zone::create(['name' => 'Zone North', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'Zone South', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    $this->orphan = Orphan::create([
        'first_name' => 'Ali',
        'last_name' => 'Bello',
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'ORP-ZN-001',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'address' => '123 Zone St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->otherOrphan = Orphan::create([
        'first_name' => 'Kano',
        'last_name' => 'Child',
        'deceased_id' => $this->otherDeceased->id,
        'reg_no' => 'ORP-ZS-001',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(8)->toDateString(),
        'address' => '456 Far St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'WID-ZN-001',
        'first_name' => 'Amina',
        'last_name' => 'Bello',
        'nin' => '12345678901',
        'phone' => '08012345678',
        'address' => '123 Zone St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->otherWidow = Widow::create([
        'deceased_id' => $this->otherDeceased->id,
        'reg_no' => 'WID-ZS-001',
        'first_name' => 'Halima',
        'last_name' => 'South',
        'nin' => '98765432109',
        'phone' => '08098765432',
        'address' => '456 Far St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->illness = Illness::create([
        'name' => 'Typhoid Fever',
        'category' => IllnessCategory::Infectious,
    ]);

    $this->actingAs($this->coordinator);
});


test('1. coordinator is blocked from rendering Healthcare Request create page', function () {
    $this->get(\App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\CreateHealthcareRequest::getUrl())
         ->assertForbidden();
});

test('2. coordinator is blocked from rendering Healthcare Request list page', function () {
    $this->get(\App\Filament\Coordinator\Resources\HealthcareRequestResource::getUrl('index'))
         ->assertForbidden();
});


