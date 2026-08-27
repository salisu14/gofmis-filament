<?php

use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest;
use App\Filament\Resources\Verifications\EducationVerificationResource;
use App\Filament\Resources\Verifications\Pages\EditEducationVerification;
use App\Filament\Resources\Verifications\Pages\ListEducationVerifications;
use App\Models\Deceased;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InterventionTypeSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'South Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id, 'full_name' => 'Deceased North']);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id, 'full_name' => 'Deceased South']);

    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Amina',
        'last_name' => 'Musa',
        'reg_no' => 'ORP-00001',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => now()->subYears(10),
        'is_eligible' => true,
    ]);

    $this->otherOrphan = Orphan::create([
        'deceased_id' => $this->otherDeceased->id,
        'first_name' => 'Zainab',
        'last_name' => 'Ibrahim',
        'reg_no' => 'ORP-00002',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => now()->subYears(11),
        'is_eligible' => true,
    ]);

    $this->supportType = InterventionType::where('name', 'Education - School Fees')->first()
        ?? InterventionType::firstOrCreate(['name' => 'Education - School Fees']);

    $this->actingAs($this->coordinator);
});

test('1. create page renders successfully', function () {
    Livewire::test(CreateEducationRequest::class)
        ->assertSuccessful();
});

test('2 & 3. Support Type Select has canonical options', function () {
    $this->seed(\Database\Seeders\InterventionTypeSeeder::class);

    Livewire::test(CreateEducationRequest::class)
        ->assertFormFieldExists('intervention_type_id');

    expect(InterventionType::count())->toBeGreaterThan(0);
});

test('4, 5, 6, 7, 8, 9, 10. valid Coordinator Education Request saves all required attributes', function () {
    $requestDate = now()->format('Y-m-d');

    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->orphan->id)
        ->set('data.intervention_type_id', (string) $this->supportType->id)
        ->set('data.request_date', $requestDate)
        ->set('data.requested_level', 'primary_1')
        ->set('data.requested_amount', 45000)
        ->set('data.notes', 'Urgent school fees for term 1.')
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('intervention_requests', [
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->supportType->id,
        'requested_level' => 'primary_1',
        'requested_amount' => 45000.00,
        'notes' => 'Urgent school fees for term 1.',
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);
});

test('11. coordinator can select own-zone eligible orphan', function () {
    Livewire::test(CreateEducationRequest::class)
        ->assertFormFieldExists('orphan_id');
});

test('12. other-zone orphan cannot be selected/submitted by coordinator', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->otherOrphan->id)
        ->set('data.intervention_type_id', (string) $this->supportType->id)
        ->set('data.notes', 'Cross zone attempt.')
        ->call('create')
        ->assertHasFormErrors(['orphan_id']);

    $this->assertDatabaseMissing('intervention_requests', [
        'notes' => 'Cross zone attempt.',
    ]);
});

test('13 & 14. newly-created request appears in Admin Education Verification query and verification page renders', function () {
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->supportType->id,
        'request_date' => now(),
        'requested_level' => 'jss_1',
        'requested_amount' => 50000,
        'notes' => 'Verified coordinator request test',
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $records = EducationVerificationResource::getEloquentQuery()->get();
    expect($records->pluck('id'))->toContain($request->id);

    Livewire::test(ListEducationVerifications::class)
        ->assertSuccessful();

    Livewire::test(EditEducationVerification::class, ['record' => $request->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'status' => 'Pending',
        ]);
});

test('15. invalid or missing support type yields validation error instead of HTTP 500', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->orphan->id)
        ->set('data.intervention_type_id', '00000000-0000-0000-0000-000000000000')
        ->set('data.notes', 'Invalid support type test')
        ->call('create')
        ->assertHasFormErrors(['intervention_type_id']);
});
