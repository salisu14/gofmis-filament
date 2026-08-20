<?php

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ViewHealthcareRequest;
use App\Models\Deceased;
use App\Models\Illness;
use App\Models\Medication;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Amina',
        'last_name' => 'Sani',
        'nin' => '12345678999',
        'reg_no' => 'ORP-00500',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'is_eligible' => true,
    ]);

    $this->illness = Illness::create([
        'name' => 'Malaria & Fever',
        'category' => IllnessCategory::Infectious->value,
    ]);

    $this->medication1 = Medication::create([
        'name' => 'Artemether / Lumefantrine',
        'code' => 'MED-001',
        'user_id' => $this->admin->id,
    ]);

    $this->medication2 = Medication::create([
        'name' => 'Paracetamol 500mg',
        'code' => 'MED-002',
        'user_id' => $this->admin->id,
    ]);
});

test('1. end-to-end healthcare request creation -> medication prescription fulfilment -> coordinator visibility', function () {
    // 1. Coordinator creates Healthcare Request
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. Abdullahi / Aminu Kano Teaching Hospital',
        'illness_id' => $this->illness->id,
        'lab_test_cost' => 5000.00,
        'drug_cost' => 7500.00,
        'prescription_date' => now()->toDateString(),
        'note' => 'Prescribed full antimalarial dosage and analgesics',
        'user_id' => $this->coordinator->id,
    ]);

    expect($prescription->total_cost)->toEqual(12500.00);

    // 2. Admin/Clinical Staff fulfills prescription with specific medications
    $prescription->medications()->attach([
        $this->medication1->id => ['dosage' => '1 tablet BD for 3 days'],
        $this->medication2->id => ['dosage' => '2 tablets TDS for 3 days'],
    ]);

    expect($prescription->medications()->count())->toBe(2);

    // 3. Coordinator views healthcare request outcome read-only
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(ViewHealthcareRequest::class, ['record' => $prescription->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Amina');
});
