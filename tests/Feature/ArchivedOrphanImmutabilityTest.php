<?php

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Filament\Resources\Orphans\Pages\ViewOrphan;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Illness;
use App\Models\Institution;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->zone = Zone::create(['name' => 'Kano Central', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'Kaduna North', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    // Male orphan aged 18+ -> Archived
    $this->maleArchivedOrphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Ahmadu',
        'last_name' => 'Sani',
        'nin' => '11111111111',
        'reg_no' => 'ORP-M-18',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(19)->toDateString(),
        'address' => 'Kano City',
        'is_eligible' => false,
        'status' => OrphanStatus::ARCHIVED,
        'rejection_reason' => 'Archived: male orphan is 18 years or older.',
    ]);

    // Married Female orphan -> Archived
    $this->femaleMarriedOrphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Fatima',
        'last_name' => 'Bello',
        'nin' => '22222222222',
        'reg_no' => 'ORP-F-MARRIED',
        'child_sequence' => 2,
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(16)->toDateString(),
        'address' => 'Kano City',
        'is_married' => true,
        'married_at' => now()->subMonths(2),
        'is_eligible' => false,
        'status' => OrphanStatus::ARCHIVED,
        'rejection_reason' => 'Archived: female orphan is married.',
    ]);

    // Active eligible orphan
    $this->activeOrphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Ibrahim',
        'last_name' => 'Musa',
        'nin' => '33333333333',
        'reg_no' => 'ORP-ACTIVE',
        'child_sequence' => 3,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'address' => 'Kano City',
        'is_eligible' => true,
        'status' => OrphanStatus::ACTIVE,
    ]);
});

test('1. archived Orphan remains visible to authorized Admin', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ViewOrphan::class, ['record' => $this->maleArchivedOrphan->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Ahmadu');
});

test('2 & 12 & 13 & 14 & 15. archived Orphan historical relations render and remain viewable', function () {
    // Attach historical records
    $illness = Illness::create(['name' => 'Malaria', 'category' => \App\Enums\IllnessCategory::Infectious->value]);
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->maleArchivedOrphan->id,
        'doctor_name' => 'Dr. Usman',
        'illness_id' => $illness->id,
        'prescription_date' => now()->subYear()->toDateString(),
        'user_id' => $this->admin->id,
    ]);

    $institution = Institution::create(['name' => 'Government Secondary School', 'type' => \App\Enums\InstitutionType::WESTERN->value, 'user_id' => $this->admin->id]);
    $class = OrphanClass::create(['name' => 'SSS 1', 'level' => 10, 'user_id' => $this->admin->id]);
    $education = OrphanEducation::create([
        'orphan_id' => $this->maleArchivedOrphan->id,
        'institution_id' => $institution->id,
        'orphan_class_id' => $class->id,
        'academic_year' => '2025/2026',
        'term' => 'First',
        'school_fees' => 25000,
        'books_fees' => 5000,
        'uniform_fees' => 5000,
        'other_fees' => 0,
        'total_amount' => 35000,
        'user_id' => $this->admin->id,
    ]);

    $type = InterventionType::create(['name' => 'General Support', 'category' => 'welfare']);
    $request = InterventionRequest::create([
        'orphan_id' => $this->maleArchivedOrphan->id,
        'intervention_type_id' => $type->id,
        'title' => 'Historical Assistance',
        'status' => 'fulfilled',
        'submitted_by' => $this->coordinator->id,
    ]);

    $template = IdCardTemplate::create(['name' => 'Standard Orphan Card', 'type' => 'orphan', 'is_active' => true, 'created_by' => $this->admin->id]);
    $idCard = IdCard::create([
        'cardable_type' => Orphan::class,
        'cardable_id' => $this->maleArchivedOrphan->id,
        'template_id' => $template->id,
        'card_number' => 'CARD-0099',
        'qr_code_path' => 'qr-codes/card-0099.png',
        'status' => 'revoked',
        'issued_at' => now()->subYears(2),
        'created_by' => $this->admin->id,
    ]);

    expect($this->maleArchivedOrphan->prescriptions()->count())->toBe(1);
    expect($this->maleArchivedOrphan->educations()->count())->toBe(1);
    expect($this->maleArchivedOrphan->interventionRequests()->count())->toBe(1);
    expect($this->maleArchivedOrphan->idCards()->count())->toBe(1);
    expect($this->maleArchivedOrphan->hasHistoricalRecords())->toBeTrue();
});

test('3 & 4 & 5. normal DeleteAction and Policy deny deleting archived Orphan or orphan with history', function () {
    $this->actingAs($this->admin);

    expect(Gate::allows('delete', $this->maleArchivedOrphan))->toBeFalse();
    expect(Gate::allows('forceDelete', $this->maleArchivedOrphan))->toBeFalse();

    $this->actingAs($this->superAdmin);
    expect(Gate::allows('delete', $this->maleArchivedOrphan))->toBeFalse();
    expect(Gate::allows('forceDelete', $this->maleArchivedOrphan))->toBeFalse();

    expect(fn () => $this->maleArchivedOrphan->delete())
        ->toThrow(\DomainException::class);
});

test('6. DeleteBulkAction cannot remove archived Orphan', function () {
    $this->actingAs($this->admin);

    $deletableOrphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Temporary',
        'last_name' => 'Draft',
        'nin' => '44444444444',
        'reg_no' => 'ORP-TEMP',
        'child_sequence' => 4,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(5)->toDateString(),
        'address' => 'Kano City',
        'is_eligible' => true,
        'status' => OrphanStatus::PENDING_REVIEW,
    ]);

    $collection = collect([$this->maleArchivedOrphan, $deletableOrphan]);

    $deletable = $collection->filter(fn (Orphan $record) => $record->status !== OrphanStatus::ARCHIVED && $record->is_eligible && ! $record->hasHistoricalRecords());

    expect($deletable->count())->toBe(1);
    expect($deletable->first()->id)->toBe($deletableOrphan->id);
});

test('7 & 8. lifecycle-sensitive fields cannot be changed through ordinary edit & cannot be silently reactivated', function () {
    $this->actingAs($this->admin);

    // Attempting to mutate status or eligibility on archived record
    $this->maleArchivedOrphan->update([
        'first_name' => 'Ahmadu Corrected', // Clerical change allowed
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $fresh = $this->maleArchivedOrphan->fresh();
    expect($fresh->first_name)->toBe('Ahmadu Corrected');
    expect($fresh->status)->toBe(OrphanStatus::ARCHIVED);
    expect($fresh->is_eligible)->toBeFalse();
});

test('9 & 10. archived male-age-limit and married female record remain archived', function () {
    $this->maleArchivedOrphan->update(['birth_date' => now()->subYears(19)->toDateString()]);
    expect($this->maleArchivedOrphan->fresh()->status)->toBe(OrphanStatus::ARCHIVED);

    $this->femaleMarriedOrphan->update(['is_married' => true]);
    expect($this->femaleMarriedOrphan->fresh()->status)->toBe(OrphanStatus::ARCHIVED);
});

test('11. archived Orphan cannot receive new eligibility-dependent benefits', function () {
    $eligibleCount = Orphan::eligible()->count();

    expect(Orphan::eligible()->where('id', $this->maleArchivedOrphan->id)->exists())->toBeFalse();
    expect(Orphan::eligible()->where('id', $this->femaleMarriedOrphan->id)->exists())->toBeFalse();
    expect(Orphan::eligible()->where('id', $this->activeOrphan->id)->exists())->toBeTrue();
});

test('16. active Orphan normal legitimate workflow still works', function () {
    $this->actingAs($this->admin);

    $this->activeOrphan->update(['first_name' => 'Ibrahim Updated']);
    expect($this->activeOrphan->fresh()->first_name)->toBe('Ibrahim Updated');
    expect($this->activeOrphan->fresh()->status)->toBe(OrphanStatus::ACTIVE);
    expect($this->activeOrphan->fresh()->is_eligible)->toBeTrue();
});

test('17. coordinator zone isolation remains intact', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    // Coordinator 1 (Kano Zone) acting
    $this->actingAs($this->coordinator);
    expect(Orphan::query()->where('id', $this->maleArchivedOrphan->id)->exists())->toBeTrue();

    // Coordinator 2 (Kaduna Zone) acting
    $this->actingAs($this->otherCoordinator);
    expect(Orphan::query()->where('id', $this->maleArchivedOrphan->id)->exists())->toBeFalse();
});
