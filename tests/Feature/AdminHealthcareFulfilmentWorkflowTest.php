<?php

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\PrescriptionStatus;
use App\Filament\Coordinator\Resources\HealthcareRequestResource;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ViewHealthcareRequest;
use App\Filament\Resources\Prescriptions\Pages\ViewPrescription;
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

test('2. admin can mark healthcare request as treated with action', function () {
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. TreatmentTest',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    expect($prescription->isPending())->toBeTrue();
    expect($prescription->isTreated())->toBeFalse();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ViewPrescription::class, ['record' => $prescription->getRouteKey()])
        ->callAction('markTreated', [
            'treated_at' => now()->toDateString(),
            'treatment_notes' => 'Medication fully administered. Patient recovered.',
        ])
        ->assertHasNoActionErrors();

    $prescription->refresh();
    expect($prescription->status)->toBe(PrescriptionStatus::TREATED);
    expect($prescription->isTreated())->toBeTrue();
    expect($prescription->treated_by_id)->toBe($this->admin->id);
    expect($prescription->treatment_notes)->toBe('Medication fully administered. Patient recovered.');
});

test('3. marking an already treated record throws domain exception', function () {
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. DoubleTreat',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    $prescription->markAsTreated('First treatment completion', now()->toDateTimeString(), $this->admin->id);

    expect(fn () => $prescription->markAsTreated('Second attempt'))
        ->toThrow(\DomainException::class, 'This healthcare request has already been marked as treated.');
});

test('4. treated healthcare record cannot be deleted', function () {
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. DeletionGuard',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    $prescription->markAsTreated('Completed treatment', now()->toDateTimeString(), $this->admin->id);

    expect(fn () => $prescription->delete())
        ->toThrow(\DomainException::class, 'Completed healthcare and treatment records cannot be deleted.');
});

test('5. coordinator cannot edit or delete treated record', function () {
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. CoordGuard',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    $prescription->markAsTreated('Done', now()->toDateTimeString(), $this->admin->id);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    expect(HealthcareRequestResource::canEdit($prescription))->toBeFalse();
    expect(HealthcareRequestResource::canDelete($prescription))->toBeFalse();
});

test('6. medication pivot records preserve historical prescription linkage', function () {
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. Preservative',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    $prescription->medications()->attach([$this->medication1->id]);

    expect(\Illuminate\Support\Facades\DB::table('medication_prescriptions')->where('prescription_id', $prescription->id)->count())->toBe(1);
    expect($prescription->medications()->first()->name)->toBe('Artemether / Lumefantrine');
});

test('7. admin prescriptions table index renders cleanly without TypeError when loading records', function () {
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. ListTest',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Resources\Prescriptions\Pages\ListPrescriptions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$prescription])
        ->assertTableActionExists('markTreated')
        ->assertTableActionExists('edit_prescription')
        ->assertTableActionExists('view');
});

test('8. action visibility rules on pending vs treated records', function () {
    $pendingPrescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. PendingVis',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);

    $treatedPrescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. TreatedVis',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);
    $treatedPrescription->markAsTreated('Finished', now()->toDateTimeString(), $this->admin->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Resources\Prescriptions\Pages\ListPrescriptions::class)
        ->assertTableActionVisible('edit_prescription', $pendingPrescription)
        ->assertTableActionVisible('markTreated', $pendingPrescription)
        ->assertTableActionVisible('delete', $pendingPrescription)
        ->assertTableActionHidden('edit_prescription', $treatedPrescription)
        ->assertTableActionHidden('markTreated', $treatedPrescription)
        ->assertTableActionHidden('delete', $treatedPrescription);
});

test('9. direct navigation to edit page for treated prescription is denied with forbidden status', function () {
    $treatedPrescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. DirectNav',
        'illness_id' => $this->illness->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
    ]);
    $treatedPrescription->markAsTreated('Finished', now()->toDateTimeString(), $this->admin->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Resources\Prescriptions\Pages\EditPrescription::class, ['record' => $treatedPrescription->getRouteKey()])
        ->assertForbidden();
});
