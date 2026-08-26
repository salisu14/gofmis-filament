<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\UserStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Role;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic UAT households.
 *
 * Creates:
 *  - development/UAT actors (super_admin, admin, coordinators per zone);
 *  - 20 Deceased households with a coherent Widow/Orphan graph covering all
 *    required eligibility scenarios.
 *
 * Idempotency: all lookups use stable natural keys (zone name, deceased
 * reg_no, widow/orphan nin + reg_no). A second run updates/keeps the same
 * records rather than duplicating them.
 */
class UatHouseholdSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedActors();
        $this->seedHouseholds();
        $this->replaceLegacyPlaceholderHouseholds();
    }

    /**
     * Deterministic UAT users and zone assignments.
     */
    protected function seedActors(): void
    {
        $devPassword = 'password123@';

        // Super admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'sadmin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($devPassword),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $superAdmin->syncRoles(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make($devPassword),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $admin->syncRoles(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        // Coordinators assigned to distinct zones.
        // The existing ZonesTableSeeder creates A1..A20 and B1..B5; we reuse
        // a deterministic subset so zone-isolation testing is meaningful.
        $coordinatorDefinitions = [
            ['email' => 'coordinator.a1@admin.com', 'name' => 'Coordinator Zone A1', 'zone' => 'A1'],
            ['email' => 'coordinator.a2@admin.com', 'name' => 'Coordinator Zone A2', 'zone' => 'A2'],
            ['email' => 'coordinator.b1@admin.com', 'name' => 'Coordinator Zone B1', 'zone' => 'B1'],
        ];

        foreach ($coordinatorDefinitions as $definition) {
            $coordinator = User::firstOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make($devPassword),
                    'status' => UserStatus::ACTIVE,
                ]
            );
            $coordinator->syncRoles(Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']));

            $zone = Zone::firstOrCreate(
                ['name' => $definition['zone']],
                ['address' => 'UAT Zone '.$definition['zone']]
            );
            $zone->update(['coordinator_id' => $coordinator->id]);
        }
    }

    /**
     * Deterministic household dataset.
     *
     * Scenarios:
     *   1. eligible widow + eligible orphan(s)
     *   2. eligible widow only
     *   3. eligible orphan(s) only
     *   4. multiple widows (schema/domain allows)
     *   5. multiple orphans of different ages/sexes
     *   6. widow ineligible (remarried)
     *   7. orphan aged out / ineligible
     *   8. no currently eligible welfare beneficiary
     *   9. households across multiple coordinator zones
     */
    protected function seedHouseholds(): void
    {
        $zoneA1 = Zone::where('name', 'A1')->first();
        $zoneA2 = Zone::where('name', 'A2')->first();
        $zoneB1 = Zone::where('name', 'B1')->first();

        $this->household(
            regNo: 'UAT-DEC-001', nin: '90000000001', firstName: 'Adamu', lastName: 'Bello',
            zone: $zoneA1, vulnerability: VulnerabilityStatus::A,
            dateOfBirth: '1965-03-12', dateOfDeath: '2022-06-15', dateRegistered: '2022-07-01',
            widows: [['Aisha', '90000000011', 'UAT-WID-001', true, false]],
            orphans: [['Musa', Gender::MALE, '2010-05-20', 'UAT-ORP-001', true, false, OrphanStatus::ACTIVE]],
            scenario: '1-eligible-widow-and-orphan'
        );

        $this->household(
            regNo: 'UAT-DEC-002', nin: '90000000002', firstName: 'Bala', lastName: 'Musa',
            zone: $zoneA1, vulnerability: VulnerabilityStatus::B,
            dateOfBirth: '1958-11-02', dateOfDeath: '2021-03-10', dateRegistered: '2021-04-05',
            widows: [['Fatima', '90000000012', 'UAT-WID-002', true, false]],
            orphans: [],
            scenario: '2-eligible-widow-only'
        );

        $this->household(
            regNo: 'UAT-DEC-003', nin: '90000000003', firstName: 'Chinedu', lastName: 'Okafor',
            zone: $zoneA1, vulnerability: VulnerabilityStatus::C,
            dateOfBirth: '1970-01-25', dateOfDeath: '2023-02-14', dateRegistered: '2023-03-01',
            widows: [],
            orphans: [
                ['Ngozi', Gender::FEMALE, '2012-08-30', 'UAT-ORP-002', true, false, OrphanStatus::ACTIVE],
                ['Emeka', Gender::MALE, '2015-12-11', 'UAT-ORP-003', true, false, OrphanStatus::ACTIVE],
            ],
            scenario: '3-eligible-orphans-only'
        );

        $this->household(
            regNo: 'UAT-DEC-004', nin: '90000000004', firstName: 'Dauda', lastName: 'Yusuf',
            zone: $zoneA1, vulnerability: VulnerabilityStatus::B,
            dateOfBirth: '1962-07-19', dateOfDeath: '2020-09-23', dateRegistered: '2020-10-12',
            widows: [
                ['Hauwa', '90000000013', 'UAT-WID-003', true, false],
                ['Laraba', '90000000014', 'UAT-WID-004', true, false],
            ],
            orphans: [],
            scenario: '4-multiple-widows'
        );

        $this->household(
            regNo: 'UAT-DEC-005', nin: '90000000005', firstName: 'Emmanuel', lastName: 'John',
            zone: $zoneA2, vulnerability: VulnerabilityStatus::A,
            dateOfBirth: '1975-04-08', dateOfDeath: '2022-11-30', dateRegistered: '2022-12-20',
            widows: [['Mary', '90000000015', 'UAT-WID-005', true, false]],
            orphans: [
                ['David', Gender::MALE, '2009-02-14', 'UAT-ORP-004', true, false, OrphanStatus::ACTIVE],
                ['Sarah', Gender::FEMALE, '2011-07-25', 'UAT-ORP-005', true, false, OrphanStatus::ACTIVE],
                ['Joseph', Gender::MALE, '2014-10-03', 'UAT-ORP-006', true, false, OrphanStatus::ACTIVE],
                ['Ruth', Gender::FEMALE, '2017-01-18', 'UAT-ORP-007', true, false, OrphanStatus::ACTIVE],
            ],
            scenario: '5-multiple-orphans-varied'
        );

        // Scenario 6: remarried widow -> ineligible
        $this->household(
            regNo: 'UAT-DEC-006', nin: '90000000006', firstName: 'Femi', lastName: 'Adeyemi',
            zone: $zoneA2, vulnerability: VulnerabilityStatus::C,
            dateOfBirth: '1968-09-17', dateOfDeath: '2021-05-05', dateRegistered: '2021-06-02',
            widows: [['Grace', '90000000016', 'UAT-WID-006', false, true]],
            orphans: [],
            scenario: '6-remarried-widow-ineligible'
        );

        // Scenario 7: orphan aged out (male >= 18)
        $this->household(
            regNo: 'UAT-DEC-007', nin: '90000000007', firstName: 'Garba', lastName: 'Ibrahim',
            zone: $zoneA2, vulnerability: VulnerabilityStatus::B,
            dateOfBirth: '1972-12-01', dateOfDeath: '2019-08-19', dateRegistered: '2019-09-10',
            widows: [],
            orphans: [
                ['Kabir', Gender::MALE, '2006-04-15', 'UAT-ORP-008', false, false, OrphanStatus::ARCHIVED],
            ],
            scenario: '7-orphan-aged-out'
        );

        // Scenario 8: no eligible beneficiary (widow ineligible + no orphans)
        $this->household(
            regNo: 'UAT-DEC-008', nin: '90000000008', firstName: 'Hassan', lastName: 'Danladi',
            zone: $zoneB1, vulnerability: VulnerabilityStatus::C,
            dateOfBirth: '1960-02-28', dateOfDeath: '2020-12-31', dateRegistered: '2021-01-15',
            widows: [['Khadija', '90000000017', 'UAT-WID-007', false, true]],
            orphans: [],
            scenario: '8-no-eligible-beneficiary'
        );

        // Scenario 9: additional households across zones for isolation testing
        $this->household(
            regNo: 'UAT-DEC-009', nin: '90000000009', firstName: 'Ibrahim', lastName: 'Suleiman',
            zone: $zoneA1, vulnerability: VulnerabilityStatus::A,
            dateOfBirth: '1966-06-21', dateOfDeath: '2022-02-11', dateRegistered: '2022-03-08',
            widows: [['Zainab', '90000000018', 'UAT-WID-008', true, false]],
            orphans: [['Aminu', Gender::MALE, '2013-09-09', 'UAT-ORP-009', true, false, OrphanStatus::ACTIVE]],
            scenario: '9-zone-isolation-a1'
        );

        $this->household(
            regNo: 'UAT-DEC-010', nin: '90000000010', firstName: 'Jibril', lastName: 'Abubakar',
            zone: $zoneB1, vulnerability: VulnerabilityStatus::B,
            dateOfBirth: '1978-10-05', dateOfDeath: '2023-07-17', dateRegistered: '2023-08-01',
            widows: [['Maimuna', '90000000019', 'UAT-WID-009', true, false]],
            orphans: [['Halima', Gender::FEMALE, '2016-03-22', 'UAT-ORP-010', true, false, OrphanStatus::ACTIVE]],
            scenario: '9-zone-isolation-b1'
        );

        // Fill the remaining target of ~20 households with additional
        // deterministic mixed scenarios (each with unique reg_no/nin).
        $extra = [
            [11, '90000000020', 'Kabiru', 'Garba', $zoneA1, 'A', '1971-05-14', '2021-10-10', '2021-11-05',
                [['Amina', '90000000030', 'UAT-WID-010', true, false]],
                [['Ishaq', Gender::MALE, '2012-06-18', 'UAT-ORP-011', true, false, OrphanStatus::ACTIVE]]],
            [12, '90000000021', 'Lawan', 'Kura', $zoneA1, 'B', '1969-08-30', '2022-04-22', '2022-05-10',
                [['Sa\'adatu', '90000000031', 'UAT-WID-011', true, false]],
                [
                    ['Fatima', Gender::FEMALE, '2015-11-02', 'UAT-ORP-012', true, false, OrphanStatus::ACTIVE],
                    ['Abdullahi', Gender::MALE, '2018-03-14', 'UAT-ORP-018', true, false, OrphanStatus::ACTIVE],
                ]],
            [13, '90000000022', 'Musa', 'Bello', $zoneA2, 'C', '1974-03-25', '2020-07-08', '2020-08-01',
                [],
                [['Umar', Gender::MALE, '2011-01-30', 'UAT-ORP-013', true, false, OrphanStatus::ACTIVE]]],
            [14, '90000000023', 'Nuhu', 'Gamawa', $zoneA2, 'A', '1963-12-09', '2021-12-24', '2022-01-12',
                [['Rakiya', '90000000032', 'UAT-WID-012', true, false]],
                [
                    ['Bilkisu', Gender::FEMALE, '2010-04-07', 'UAT-ORP-014', true, false, OrphanStatus::ACTIVE],
                    ['Yusuf', Gender::MALE, '2016-09-19', 'UAT-ORP-019', true, false, OrphanStatus::ACTIVE],
                ]],
            [15, '90000000024', 'Oluwaseun', 'Adebayo', $zoneB1, 'B', '1979-09-11', '2022-09-02', '2022-09-28',
                [['Funmilayo', '90000000033', 'UAT-WID-013', true, false]],
                []],
            [16, '90000000025', 'Peter', 'Yakubu', $zoneB1, 'C', '1982-01-19', '2023-03-13', '2023-04-03',
                [],
                [['Esther', Gender::FEMALE, '2014-08-26', 'UAT-ORP-015', true, false, OrphanStatus::ACTIVE]]],
            [17, '90000000026', 'Rabiu', 'Danjuma', $zoneA1, 'B', '1967-07-07', '2019-06-30', '2019-07-22',
                [['Safiya', '90000000034', 'UAT-WID-014', true, false]],
                []],
            [18, '90000000027', 'Sani', 'Tukur', $zoneA2, 'A', '1970-11-23', '2023-01-05', '2023-01-30',
                [['Yasmin', '90000000035', 'UAT-WID-015', true, false]],
                [
                    ['Zaharaddeen', Gender::MALE, '2013-07-12', 'UAT-ORP-016', true, false, OrphanStatus::ACTIVE],
                    ['Maryam', Gender::FEMALE, '2019-02-08', 'UAT-ORP-020', true, false, OrphanStatus::ACTIVE],
                ]],
            [19, '90000000028', 'Tunde', 'Balogun', $zoneB1, 'A', '1976-02-16', '2020-05-19', '2020-06-10',
                [],
                [
                    ['Blessing', Gender::FEMALE, '2011-10-21', 'UAT-ORP-017', true, false, OrphanStatus::ACTIVE],
                    ['Samuel', Gender::MALE, '2017-06-30', 'UAT-ORP-021', true, false, OrphanStatus::ACTIVE],
                ]],
            [20, '90000000029', 'Umar', 'Sanda', $zoneA1, 'C', '1965-10-03', '2023-05-11', '2023-06-02',
                [['Hafsat', '90000000036', 'UAT-WID-016', false, true]],
                []],
        ];

        foreach ($extra as [$n, $nin, $first, $last, $zone, $vuln, $dob, $dod, $dod2, $widows, $orphans]) {
            $this->household(
                regNo: 'UAT-DEC-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                nin: $nin, firstName: $first, lastName: $last,
                zone: $zone, vulnerability: VulnerabilityStatus::from($vuln),
                dateOfBirth: $dob, dateOfDeath: $dod, dateRegistered: $dod2,
                widows: $widows, orphans: $orphans,
                scenario: 'extra-'.$n
            );
        }
    }

    /**
     * Create (or converge) one deterministic household.
     *
     * Uses updateOrCreate keyed on the stable UAT reg_no so that re-running
     * the seeder repairs UAT-owned records to their canonical deterministic
     * definition (including the displayed full_name column) without touching
     * arbitrary real records, deleting data, or regenerating UUIDs.
     *
     * @param  array<int, array{0:string,1:string,2:string,3:bool,4:bool}>  $widows
     * @param  array<int, array{0:string,1:Gender,2:string,3:string,4:bool,5:bool,6:OrphanStatus}>  $orphans
     */
    protected function household(
        string $regNo,
        string $nin,
        string $firstName,
        string $lastName,
        Zone $zone,
        VulnerabilityStatus $vulnerability,
        string $dateOfBirth,
        string $dateOfDeath,
        string $dateRegistered,
        array $widows,
        array $orphans,
        string $scenario,
    ): void {
        $fullName = trim($firstName.' '.$lastName);

        $deceased = Deceased::updateOrCreate(
            ['reg_no' => $regNo],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'nin' => $nin,
                'guardian_name' => 'Guardian of '.$lastName,
                'guardian_phone' => '08012345'.substr($nin, -4),
                'vulnerability_status' => $vulnerability,
                'date_of_birth' => $dateOfBirth,
                'date_of_death' => $dateOfDeath,
                'date_registered' => $dateRegistered,
                'zone_id' => $zone->id,
                'number_of_orphans_left' => count($orphans),
                'number_of_widows_left' => count($widows),
            ]
        );

        foreach ($widows as $index => [$wFirstName, $wNin, $wRegNo, $eligible, $married]) {
            Widow::updateOrCreate(
                ['reg_no' => $wRegNo],
                [
                    'first_name' => $wFirstName,
                    'last_name' => $lastName,
                    'full_name' => trim($wFirstName.' '.$lastName),
                    'nin' => $wNin,
                    'deceased_id' => $deceased->id,
                    'child_sequence' => $index + 1,
                    'is_eligible' => $eligible,
                    'is_married' => $married,
                ]
            );
        }

        foreach ($orphans as $index => [$oFirstName, $gender, $birthDate, $oRegNo, $eligible, $married, $status]) {
            Orphan::updateOrCreate(
                ['reg_no' => $oRegNo],
                [
                    'first_name' => $oFirstName,
                    'last_name' => $lastName,
                    'full_name' => trim($oFirstName.' '.$lastName),
                    'gender' => $gender,
                    'birth_date' => $birthDate,
                    'deceased_id' => $deceased->id,
                    'child_sequence' => $index + 1,
                    'is_eligible' => $eligible,
                    'is_married' => $married,
                    'status' => $status,
                ]
            );
        }

        $this->command?->info("  Household {$regNo} [{$scenario}]");
    }

    /**
     * Replace the legacy placeholder households created by the baseline
     * WelfarePackageSeeder ("DeceasedFirst N / DeceasedLast N").
     *
     * These records predate the deterministic UAT dataset and are not part of
     * the UAT-* fixtures, but they still appear in the Deceased module and look
     * artificial. This method:
     *
     *  1. RENAMES (updateOrCreate on the stable DEC-* reg_no) every legacy
     *     record that is referenced by other rows (welfare_beneficiaries,
     *     prescriptions, etc.) with a realistic deterministic Nigerian name —
     *     preserving reg_no, NIN, relationships and all history/workflow.
     *  2. REMOVES only legacy records that are COMPLETELY UNREFERENCED
     *     (no widows, no orphans, no welfare beneficiaries, no prescriptions).
     *
     * Deterministic and idempotent: a second run finds nothing to rename (names
     * already correct) and nothing to remove (already gone).
     */
    protected function replaceLegacyPlaceholderHouseholds(): void
    {
        // Deterministic realistic names keyed by the legacy reg_no. These are
        // fictional demo identities; family surnames are coherent per record.
        $legacyNames = [
            'DEC-00001' => ['Sani', 'Musa'],
            'DEC-00002' => ['Ibrahim', 'Lawal'],
            'DEC-00003' => ['Kabiru', 'Danladi'],
            'DEC-00004' => ['Musa', 'Garba'],
            'DEC-00005' => ['Yusuf', 'Ado'],
            'DEC-00006' => ['Ahmad', 'Suleiman'],
            'DEC-00007' => ['Umar', 'Bala'],
            'DEC-00008' => ['Nasiru', 'Abdullahi'],
            'DEC-00009' => ['Salisu', 'Ibrahim'],
            'DEC-00010' => ['Bello', 'Muhammad'],
            'DEC-00011' => ['Abba', 'Kura'],
            'DEC-00012' => ['Tijjani', 'Musa'],
            'DEC-00013' => ['Isiyaku', 'Sani'],
            'DEC-00014' => ['Garba', 'Yakubu'],
            'DEC-00015' => ['Sule', 'Garba'],
            'DEC-00016' => ['Adamu', 'Bala'],
            'DEC-00017' => ['Lawal', 'Danjuma'],
            'DEC-00018' => ['Murtala', 'Bello'],
            'DEC-00019' => ['Rabiu', 'Sani'],
            'DEC-00020' => ['Saidu', 'Musa'],
        ];

        $legacyRecords = Deceased::where('reg_no', 'like', 'DEC-%')->get();

        foreach ($legacyRecords as $record) {
            $regNo = $record->reg_no;
            $hasPlaceholderName = str_starts_with((string) $record->first_name, 'DeceasedFirst')
                || str_starts_with((string) $record->last_name, 'DeceasedLast');

            if (! $hasPlaceholderName) {
                continue;
            }

            $referenced = $record->widows()->exists()
                || $record->orphans()->exists()
                || \App\Models\WelfareBeneficiary::where('deceased_id', $record->id)->exists()
                || \App\Models\Prescription::where('prescribable_type', Deceased::class)
                    ->where('prescribable_id', $record->id)
                    ->exists();

            if ($referenced) {
                // Rename in place — preserve all relationships/history.
                $name = $legacyNames[$regNo] ?? ['Unknown', 'Legacy'];

                $record->update([
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'full_name' => trim($name[0].' '.$name[1]),
                ]);

                $this->command?->info("  Renamed legacy household {$regNo} -> {$name[0]} {$name[1]}");
            } else {
                // Completely unreferenced — safe to remove.
                $record->forceDelete();
                $this->command?->info("  Removed unreferenced legacy household {$regNo}");
            }
        }
    }
}
