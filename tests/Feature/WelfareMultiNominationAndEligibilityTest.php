<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\UserStatus;
use App\Enums\WelfarePackageStatus;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\Welfare\WelfareNominationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

    $this->zoneA = Zone::create(['name' => 'Zone North', 'code' => 'ZN-01']);
    $this->zoneB = Zone::create(['name' => 'Zone South', 'code' => 'ZS-02']);

    $this->admin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
    ]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create([
        'status' => UserStatus::ACTIVE,
    ]);
    $this->coordinator->assignRole('coordinator');
    $this->zoneA->update(['coordinator_id' => $this->coordinator->id]);

    $this->package = WelfarePackage::create([
        'name' => 'Sallah Food Support 2026',
        'description' => 'Annual food package distribution',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);
});

// A1. Admin can nominate multiple eligible beneficiaries
test('A1. admin can nominate multiple eligible beneficiaries for open welfare package', function () {
    $dec1 = Deceased::create([
        'first_name' => 'Adamu',
        'last_name' => 'Bello',
        'nin' => '10000000001',
        'reg_no' => 'DEC-W001',
        'guardian_name' => 'Guardian Adamu',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(6),
        'date_of_death' => now()->subMonths(7),
        'date_of_birth' => now()->subYears(40),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::A,
    ]);
    Widow::create([
        'first_name' => 'Aminat',
        'last_name' => 'Bello',
        'nin' => '11111111101',
        'reg_no' => 'WID-001',
        'child_sequence' => 1,
        'deceased_id' => $dec1->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $dec2 = Deceased::create([
        'first_name' => 'Sani',
        'last_name' => 'Usman',
        'nin' => '10000000002',
        'reg_no' => 'DEC-W002',
        'guardian_name' => 'Guardian Sani',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(4),
        'date_of_death' => now()->subMonths(5),
        'date_of_birth' => now()->subYears(35),
        'zone_id' => $this->zoneB->id,
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
    ]);
    Orphan::create([
        'first_name' => 'Zainab',
        'last_name' => 'Usman',
        'reg_no' => 'ORP-001',
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(10),
        'deceased_id' => $dec2->id,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $service = app(WelfareNominationService::class);
    $result = $service->nominate($this->package->id, [$dec1->id, $dec2->id], $this->admin);

    expect($result['nominated_count'])->toBe(2)
        ->and($result['duplicates_count'])->toBe(0)
        ->and($result['ineligible_count'])->toBe(0);

    expect(WelfareBeneficiary::where('welfare_package_id', $this->package->id)->count())->toBe(2);
});

// A2. Duplicate nomination is skipped cleanly
test('A2. duplicate nomination for same open welfare package is skipped cleanly', function () {
    $dec1 = Deceased::create([
        'first_name' => 'Kabila',
        'last_name' => 'John',
        'nin' => '10000000003',
        'reg_no' => 'DEC-W003',
        'guardian_name' => 'Guardian Kabila',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(2),
        'date_of_death' => now()->subMonths(3),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::A,
    ]);
    Widow::create([
        'first_name' => 'Fatima',
        'last_name' => 'John',
        'nin' => '11111111102',
        'reg_no' => 'WID-002',
        'child_sequence' => 1,
        'deceased_id' => $dec1->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $service = app(WelfareNominationService::class);

    // First nomination
    $result1 = $service->nominate($this->package->id, [$dec1->id], $this->admin);
    expect($result1['nominated_count'])->toBe(1);

    // Second nomination attempt for same package & deceased
    $result2 = $service->nominate($this->package->id, [$dec1->id], $this->admin);
    expect($result2['nominated_count'])->toBe(0)
        ->and($result2['duplicates_count'])->toBe(1);
});

// A3. Ineligible household (only remarried widow) is rejected
test('A3. household with only remarried widow is rejected during nomination', function () {
    $dec1 = Deceased::create([
        'first_name' => 'Musa',
        'last_name' => 'Garba',
        'nin' => '10000000004',
        'reg_no' => 'DEC-W004',
        'guardian_name' => 'Guardian Musa',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(2),
        'date_of_death' => now()->subMonths(3),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::A,
    ]);
    Widow::create([
        'first_name' => 'Aisha',
        'last_name' => 'Garba',
        'nin' => '11111111103',
        'reg_no' => 'WID-003',
        'child_sequence' => 1,
        'deceased_id' => $dec1->id,
        'is_eligible' => false,
        'is_married' => true,
        'married_at' => now()->subDays(30),
    ]);

    $service = app(WelfareNominationService::class);
    $result = $service->nominate($this->package->id, [$dec1->id], $this->admin);

    expect($result['nominated_count'])->toBe(0)
        ->and($result['ineligible_count'])->toBe(1);
});

// A4. Coordinator cross-zone beneficiary nomination is rejected server-side
test('A4. coordinator cannot nominate beneficiary outside assigned zone', function () {
    $outOfZoneDec = Deceased::create([
        'first_name' => 'Ibrahim',
        'last_name' => 'Tanko',
        'nin' => '10000000005',
        'reg_no' => 'DEC-W005',
        'guardian_name' => 'Guardian Ibrahim',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(2),
        'date_of_death' => now()->subMonths(3),
        'zone_id' => $this->zoneB->id, // Zone B, but coordinator manages Zone A
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::A,
    ]);
    Widow::create([
        'first_name' => 'Hawa',
        'last_name' => 'Tanko',
        'nin' => '11111111104',
        'reg_no' => 'WID-004',
        'child_sequence' => 1,
        'deceased_id' => $outOfZoneDec->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $service = app(WelfareNominationService::class);
    $result = $service->nominate($this->package->id, [$outOfZoneDec->id], $this->coordinator);

    expect($result['nominated_count'])->toBe(0)
        ->and($result['ineligible_count'])->toBe(1);
});

// B1. Deceased date validation: death in future is rejected
test('B1. deceased date of death in future throws InvalidArgumentException', function () {
    expect(function () {
        Deceased::create([
            'first_name' => 'Future',
            'last_name' => 'Test',
            'nin' => '10000000006',
            'reg_no' => 'DEC-FD01',
            'guardian_name' => 'Guardian Test',
            'guardian_phone' => '08012345678',
            'date_registered' => now(),
            'date_of_death' => now()->addDays(5),
            'zone_id' => $this->zoneA->id,
            'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
        ]);
    })->toThrow(\InvalidArgumentException::class, 'Date of Death cannot be in the future.');
});

// B2. Deceased date validation: registration before death is rejected
test('B2. deceased date registered earlier than date of death throws InvalidArgumentException', function () {
    expect(function () {
        Deceased::create([
            'first_name' => 'RegError',
            'last_name' => 'Test',
            'nin' => '10000000007',
            'reg_no' => 'DEC-FD02',
            'guardian_name' => 'Guardian Test',
            'guardian_phone' => '08012345678',
            'date_registered' => Carbon::parse('2025-04-20'),
            'date_of_death' => Carbon::parse('2025-04-23'),
            'zone_id' => $this->zoneA->id,
            'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
        ]);
    })->toThrow(\InvalidArgumentException::class, 'Date Registered cannot be earlier than Date of Death.');
});

// B3. Deceased date of death before date of birth is rejected
test('B3. deceased date of death earlier than date of birth throws InvalidArgumentException', function () {
    expect(function () {
        Deceased::create([
            'first_name' => 'BirthError',
            'last_name' => 'Test',
            'nin' => '10000000008',
            'reg_no' => 'DEC-FD03',
            'guardian_name' => 'Guardian Test',
            'guardian_phone' => '08012345678',
            'date_of_birth' => Carbon::parse('2026-08-21'),
            'date_of_death' => Carbon::parse('2025-04-23'),
            'date_registered' => Carbon::parse('2025-04-25'),
            'zone_id' => $this->zoneA->id,
            'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
        ]);
    })->toThrow(\InvalidArgumentException::class, 'Date of Death cannot be earlier than Date of Birth.');
});

// B4. Legacy missing DOB calculates age_at_death from death date, never today
test('B4. age_at_death calculates from date_of_birth to date_of_death, never today', function () {
    $dec = Deceased::create([
        'first_name' => 'Legacy',
        'last_name' => 'AgeTest',
        'nin' => '10000000009',
        'reg_no' => 'DEC-LEG01',
        'guardian_name' => 'Guardian Test',
        'guardian_phone' => '08012345678',
        'date_of_birth' => Carbon::parse('1960-01-01'),
        'date_of_death' => Carbon::parse('2020-01-01'),
        'date_registered' => Carbon::parse('2020-02-01'),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
    ]);

    expect($dec->age_at_death)->toBe(60);
});

// C1. Domain eligibility helpers: Widow & Orphan
test('C1. domain eligibility helpers correctly evaluate operational and eligible status', function () {
    $dec = Deceased::create([
        'first_name' => 'Elig',
        'last_name' => 'Test',
        'nin' => '10000000010',
        'reg_no' => 'DEC-ELIG01',
        'guardian_name' => 'Guardian Test',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(1),
        'date_of_death' => now()->subMonths(2),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
    ]);

    $activeWidow = Widow::create([
        'first_name' => 'Active',
        'last_name' => 'Widow',
        'nin' => '22222222201',
        'reg_no' => 'WID-005',
        'child_sequence' => 1,
        'deceased_id' => $dec->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);
    expect($activeWidow->isOperationalBeneficiary())->toBeTrue()
        ->and($activeWidow->isEligibleForSupport())->toBeTrue();

    $remarriedWidow = Widow::create([
        'first_name' => 'Remarried',
        'last_name' => 'Widow',
        'nin' => '22222222202',
        'reg_no' => 'WID-006',
        'child_sequence' => 2,
        'deceased_id' => $dec->id,
        'is_eligible' => false,
        'is_married' => true,
        'married_at' => now()->subDays(10),
    ]);
    expect($remarriedWidow->isOperationalBeneficiary())->toBeFalse()
        ->and($remarriedWidow->isEligibleForSupport())->toBeFalse();

    $maleOrphan = Orphan::create([
        'first_name' => 'Male',
        'last_name' => 'Overaged',
        'reg_no' => 'ORP-002',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(19),
        'deceased_id' => $dec->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
        'is_married' => false,
    ]);
    expect($maleOrphan->isOverAged())->toBeTrue()
        ->and($maleOrphan->isOperationalBeneficiary())->toBeFalse()
        ->and($maleOrphan->isEligibleForSupport())->toBeFalse();

    $femaleMarriedOrphan = Orphan::create([
        'first_name' => 'Female',
        'last_name' => 'Married',
        'reg_no' => 'ORP-003',
        'child_sequence' => 2,
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(16),
        'deceased_id' => $dec->id,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
        'is_married' => true,
    ]);
    expect($femaleMarriedOrphan->isOperationalBeneficiary())->toBeFalse()
        ->and($femaleMarriedOrphan->isEligibleForSupport())->toBeFalse();
});
