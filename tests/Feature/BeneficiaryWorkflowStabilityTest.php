<?php

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\OrphanStatus;
use App\Filament\Resources\Deceased\RelationManagers\OrphansRelationManager;
use App\Filament\Resources\Orphans\OrphanResource;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Zone;
use App\Services\IdCardGenerationService;
use App\Services\IdCardPDFService;
use App\Services\OrphanStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

if (!function_exists('createTestOrphan')) {
    function createTestOrphan(array $attributes = []): Orphan
    {
        static $seq = 1;

        return Orphan::create(array_merge([
            'first_name' => 'Child'.$seq,
            'last_name' => 'Orphan'.$seq,
            'reg_no' => 'ORP-'.sprintf('%05d', $seq),
            'child_sequence' => $seq++,
            'gender' => Gender::MALE,
            'birth_date' => now()->subYears(10)->toDateString(),
            'address' => '123 Main Street',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ], $attributes));
    }
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'Test Zone '.rand(1000, 9999), 'address' => '123 Test St']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->template = IdCardTemplate::create([
        'name' => 'Default Orphan Template',
        'type' => 'orphan',
        'is_active' => true,
    ]);

    $this->illness = Illness::create([
        'name' => 'Malaria',
        'category' => IllnessCategory::Other,
    ]);
});

test('orphan model no longer hides archived or pending orphans from global queries', function () {
    $active = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $archived = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $pending = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'status' => OrphanStatus::PENDING_REVIEW,
        'is_eligible' => false,
    ]);

    $all = Orphan::all();

    expect($all)->toHaveCount(3)
        ->and($all->pluck('id'))->toContain($active->id, $archived->id, $pending->id);
});

test('eligible scope correctly filters down to active and eligible orphans', function () {
    $eligibleMale = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $overagedMale = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(19)->toDateString(),
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $archivedFemale = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(12)->toDateString(),
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $eligibleOrphans = Orphan::eligible()->get();

    expect($eligibleOrphans)->toHaveCount(1)
        ->and($eligibleOrphans->first()->id)->toBe($eligibleMale->id);
});

test('id card cardable relation resolves archived and soft deleted beneficiaries', function () {
    $archivedOrphan = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $idCard = IdCard::create([
        'card_number' => 'GOF-O-'.rand(1000, 9999),
        'cardable_type' => Orphan::class,
        'cardable_id' => $archivedOrphan->id,
        'template_id' => $this->template->id,
        'qr_code_path' => 'qrcodes/test.png',
        'issued_at' => now(),
        'status' => 'draft',
    ]);

    expect($idCard->fresh()->cardable)->not->toBeNull()
        ->and($idCard->fresh()->cardable->id)->toBe($archivedOrphan->id);
});

test('prescription and education relations resolve historical non-eligible orphans', function () {
    $archivedOrphan = createTestOrphan([
        'deceased_id' => $this->deceased->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $prescription = Prescription::create([
        'doctor_name' => 'Dr. Test',
        'illness_id' => $this->illness->id,
        'illness' => 'Malaria',
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $archivedOrphan->id,
        'user_id' => $this->admin->id,
        'prescription_date' => now()->toDateString(),
        'lab_test_cost' => 0,
        'drug_cost' => 0,
    ]);

    $institution = \App\Models\Institution::create([
        'name' => 'Test School',
        'type' => \App\Enums\InstitutionType::WESTERN,
    ]);

    $education = OrphanEducation::create([
        'orphan_id' => $archivedOrphan->id,
        'institution_id' => $institution->id,
        'school_fee' => 1000,
        'fee_frequency' => 'termly',
        'is_current' => true,
    ]);

    expect($prescription->fresh()->prescribable)->not->toBeNull()
        ->and($prescription->fresh()->prescribable->id)->toBe($archivedOrphan->id)
        ->and($education->fresh()->orphan)->not->toBeNull()
        ->and($education->fresh()->orphan->id)->toBe($archivedOrphan->id);
});

test('deceased parent orphans relationship returns all children regardless of eligibility status', function () {
    $parent = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $active = createTestOrphan([
        'deceased_id' => $parent->id,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $archived = createTestOrphan([
        'deceased_id' => $parent->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    expect($parent->orphans)->toHaveCount(2)
        ->and($parent->eligibleOrphans)->toHaveCount(1);
});

test('orphan status service handles approve reject and archive transitions cleanly', function () {
    $service = app(OrphanStatusService::class);

    $pending = createTestOrphan([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'status' => OrphanStatus::PENDING_REVIEW,
        'is_eligible' => false,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
    ]);

    $service->approve($pending, $this->admin);
    expect($pending->fresh()->status)->toBe(OrphanStatus::ACTIVE)
        ->and($pending->fresh()->is_eligible)->toBeTrue();

    $pending2 = createTestOrphan([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'status' => OrphanStatus::PENDING_REVIEW,
        'is_eligible' => false,
    ]);

    $service->reject($pending2, $this->admin, 'Incomplete documentation');
    expect($pending2->fresh()->status)->toBe(OrphanStatus::REJECTED)
        ->and($pending2->fresh()->is_eligible)->toBeFalse()
        ->and($pending2->fresh()->rejection_reason)->toBe('Incomplete documentation');

    $service->archive($pending->fresh(), $this->admin, 'Aged out');
    expect($pending->fresh()->status)->toBe(OrphanStatus::ARCHIVED)
        ->and($pending->fresh()->is_eligible)->toBeFalse();
});

test('id card generation service prevents duplicate open card generation and rejects ineligible beneficiaries', function () {
    $service = app(IdCardGenerationService::class);

    $activeOrphan = createTestOrphan([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
    ]);

    $card1 = $service->generateCard($activeOrphan, $this->template);
    expect($card1)->toBeInstanceOf(IdCard::class)
        ->and($card1->status)->toBe('draft');

    // Attempt second card while first card is draft -> must throw ValidationException
    expect(fn () => $service->generateCard($activeOrphan, $this->template))
        ->toThrow(ValidationException::class);

    // Ineligible orphan -> must throw ValidationException
    $ineligibleOrphan = createTestOrphan([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    expect(fn () => $service->generateCard($ineligibleOrphan))
        ->toThrow(ValidationException::class);
});

test('id card activation and markAsPrinted refuse ineligible beneficiaries', function () {
    $archivedOrphan = createTestOrphan([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $card = IdCard::create([
        'card_number' => 'GOF-O-'.rand(1000, 9999),
        'cardable_type' => Orphan::class,
        'cardable_id' => $archivedOrphan->id,
        'template_id' => $this->template->id,
        'qr_code_path' => 'qrcodes/test.png',
        'issued_at' => now(),
        'status' => 'draft',
    ]);

    expect(fn () => $card->activate())
        ->toThrow(ValidationException::class);

    expect(fn () => $card->markAsPrinted())
        ->toThrow(ValidationException::class);
});

test('orphans relation manager declares related resource', function () {
    $reflection = new ReflectionProperty(OrphansRelationManager::class, 'relatedResource');
    $reflection->setAccessible(true);
    $relatedResource = $reflection->getValue();

    expect($relatedResource)->toBe(OrphanResource::class);
});

test('id card pdf service prepares data for active or archived cardable without throwing exception', function () {
    $archivedOrphan = createTestOrphan([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $idCard = IdCard::create([
        'card_number' => 'GOF-O-'.rand(1000, 9999),
        'cardable_type' => Orphan::class,
        'cardable_id' => $archivedOrphan->id,
        'template_id' => $this->template->id,
        'qr_code_path' => 'qrcodes/test.png',
        'issued_at' => now(),
        'status' => 'draft',
    ]);

    $pdfService = app(IdCardPDFService::class);
    $data = $pdfService->prepareCardDataForBrowser($idCard);

    expect($data)->toBeArray()
        ->and($data['full_name'])->toBe($archivedOrphan->full_name);
});
