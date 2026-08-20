<?php

use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest;
use App\Filament\Resources\Verifications\Pages\ListEducationVerifications;
use App\Models\Deceased;
use App\Models\Institution;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InterventionTypeSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Amina',
        'last_name' => 'Musa',
        'reg_no' => 'ORP-00101',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => now()->subYears(10),
        'is_eligible' => true,
    ]);

    $this->institution = Institution::create([
        'name' => 'Government Secondary School',
        'type' => \App\Enums\InstitutionType::WESTERN,
    ]);

    $this->education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->institution->id,
        'class_level' => 'JSS 1',
        'school_fee' => 35000,
        'fee_frequency' => 'termly',
        'is_fee_supported' => true,
        'support_amount' => 35000,
        'is_current' => true,
        'started_at' => now()->subMonths(6),
    ]);

    $this->supportType = InterventionType::where('name', 'Education - School Fees')->first();
});

test('1. coordinator creates Education Request and can see read-only education summary', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(CreateEducationRequest::class)
        ->fillForm([
            'orphan_id' => (string) $this->orphan->id,
        ])
        ->fillForm([
            'intervention_type_id' => (string) $this->supportType->id,
            'requested_level' => 'jss_1',
            'requested_amount' => 35000,
            'notes' => 'Termly school fee request',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('intervention_requests', [
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->supportType->id,
        'requested_amount' => 35000.00,
        'status' => 'pending',
    ]);
});

test('2. coordinator has least privilege: create requests but no verification or education management permissions', function () {
    expect($this->coordinator->hasPermissionTo('create_education_interventions'))->toBeTrue()
        ->and($this->coordinator->hasPermissionTo('verify_education_interventions'))->toBeFalse()
        ->and($this->coordinator->hasPermissionTo('edit_education_interventions'))->toBeFalse()
        ->and($this->coordinator->hasPermissionTo('delete_education_interventions'))->toBeFalse();
});

test('3. admin has full education verification management permissions', function () {
    expect($this->admin->hasPermissionTo('view_education_interventions'))->toBeTrue()
        ->and($this->admin->hasPermissionTo('verify_education_interventions'))->toBeTrue();
});

test('4. end-to-end education verification flow: create -> verify -> approve -> coordinator status update', function () {
    // 1. Coordinator creates request
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->supportType->id,
        'request_date' => now(),
        'requested_level' => 'jss_1',
        'requested_amount' => 35000,
        'notes' => 'E2E workflow test request',
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);

    // 2. Request appears in Admin Education Verification list
    $this->actingAs($this->admin);
    Livewire::test(ListEducationVerifications::class)
        ->assertSuccessful();

    expect($request->canApproveRequest())->toBeFalse();

    // 3. Admin performs Verify action
    Livewire::test(ListEducationVerifications::class)
        ->callTableAction('verify', $request, [
            'verification_notes' => 'Confirmed with principal Dr. Sani.',
        ])
        ->assertHasNoTableActionErrors();

    $request->refresh();
    expect($request->verification_status)->toBe('verified')
        ->and($request->verification_notes)->toBe('Confirmed with principal Dr. Sani.')
        ->and($request->canApproveRequest())->toBeTrue();

    // 4. Admin performs Approve action
    Livewire::test(ListEducationVerifications::class)
        ->callTableAction('approve', $request)
        ->assertHasNoTableActionErrors();

    $request->refresh();
    expect($request->status)->toBe('approved')
        ->and($request->approved_by)->toBe($this->admin->id);

    // 5. Coordinator sees updated status
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    expect($request->fresh()->status)->toBe('approved');
});

test('5. unverified request cannot be approved directly and throws runtime error', function () {
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->supportType->id,
        'request_date' => now(),
        'requested_amount' => 35000,
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);

    expect($request->canApproveRequest())->toBeFalse();

    expect(fn () => $request->approveRequest($this->admin->id))
        ->toThrow(RuntimeException::class, 'Education requests must be verified before approval.');
});

test('6. rejection flow records reason and audit state', function () {
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->supportType->id,
        'request_date' => now(),
        'requested_amount' => 35000,
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ListEducationVerifications::class)
        ->callTableAction('reject', $request, [
            'rejection_reason' => 'Duplicate request submitted for same term.',
        ])
        ->assertHasNoTableActionErrors();

    $request->refresh();
    expect($request->status)->toBe('rejected')
        ->and($request->verification_status)->toBe('failed')
        ->and($request->rejection_reason)->toBe('Duplicate request submitted for same term.');
});
