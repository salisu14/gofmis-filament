<?php

namespace Database\Seeders;

use App\Enums\InstitutionType;
use App\Enums\InterventionStatus;
use App\Enums\PrescriptionStatus;
use App\Models\Institution;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deterministic UAT cross-module history (education, healthcare,
 * interventions) for a selected subset of orphans — sufficient to exercise a
 * future Comprehensive Orphan Profile Report.
 *
 * Idempotency: each record type is keyed by a deterministic natural reference
 * (education reference, prescription doctor+date+orphan, intervention
 * request type+orphan+date).
 */
class UatEducationHealthcareSeeder extends Seeder
{
    /**
     * Fixed anchor date so that prescription and intervention request dates
     * are deterministic across runs and days (same seed command always
     * produces the same records; second runs never duplicate).
     */
    protected const ANCHOR_DATE = '2026-07-01';

    protected ?User $admin = null;

    public function run(): void
    {
        $this->admin = User::where('email', 'admin@admin.com')->first()
            ?? User::where('email', 'sadmin@admin.com')->first();

        if (! $this->admin) {
            throw new \RuntimeException('UatEducationHealthcareSeeder requires an admin user.');
        }

        $this->seedOrphanClasses();
        $this->seedInstitutions();
        $this->seedEducation();
        $this->seedHealthcare();
        $this->seedInterventions();
    }

    /**
     * Ensure orphan classes exist (deterministic, idempotent).
     */
    protected function seedOrphanClasses(): void
    {
        $classNames = [
            'Primary 1', 'Primary 2', 'Primary 3',
            'Primary 4', 'Primary 5', 'Primary 6',
            'JSS I', 'JSS II', 'JSS III',
            'SS I', 'SS II', 'SS III',
        ];

        foreach ($classNames as $name) {
            OrphanClass::firstOrCreate(
                ['name' => $name],
                ['user_id' => $this->admin->id]
            );
        }
    }

    protected function seedInstitutions(): void
    {
        $institutions = [
            ['name' => 'Garko Central Primary School', 'type' => InstitutionType::WESTERN, 'address' => 'Garko Central'],
            ['name' => 'Garko Community Secondary School', 'type' => InstitutionType::WESTERN, 'address' => 'Garko North'],
            ['name' => 'Tudun Wada Islamiyya School', 'type' => InstitutionType::ISLAMIYYA, 'address' => 'Tudun Wada'],
            ['name' => 'Garko Vocational Training Centre', 'type' => InstitutionType::VOCATIONAL, 'address' => 'Garko South'],
        ];

        foreach ($institutions as $data) {
            Institution::firstOrCreate(['name' => $data['name']], [
                'type' => $data['type'],
                'address' => $data['address'],
            ]);
        }
    }

    protected function seedEducation(): void
    {
        $primary = OrphanClass::where('name', 'Primary 3')->first();
        $primary4 = OrphanClass::where('name', 'Primary 4')->first();
        $jss = OrphanClass::where('name', 'JSS I')->first();
        $ss = OrphanClass::where('name', 'SS I')->first();

        $primarySchool = Institution::where('name', 'Garko Central Primary School')->first();
        $secondarySchool = Institution::where('name', 'Garko Community Secondary School')->first();

        // [orphan reg_no, institution, class, class_level, school_fee, support_amount]
        $enrollments = [
            ['UAT-ORP-001', $primarySchool, $primary4, 'Primary 4', 15000, 15000],
            ['UAT-ORP-002', $primarySchool, $primary4, 'Primary 4', 15000, 10000],
            ['UAT-ORP-003', $primarySchool, $primary, 'Primary 3', 12000, 12000],
            ['UAT-ORP-004', $secondarySchool, $jss, 'JSS I', 25000, 25000],
            ['UAT-ORP-005', $secondarySchool, $jss, 'JSS I', 25000, 20000],
            ['UAT-ORP-010', $primarySchool, $primary, 'Primary 3', 12000, 12000],
            ['UAT-ORP-013', $secondarySchool, $ss, 'SS I', 35000, 35000],
        ];

        foreach ($enrollments as [$regNo, $institution, $class, $classLevel, $fee, $support]) {
            $orphan = Orphan::where('reg_no', $regNo)->first();
            if (! $orphan) {
                continue;
            }

            OrphanEducation::firstOrCreate(
                ['reference' => 'UAT-EDU-'.$regNo],
                [
                    'orphan_id' => $orphan->id,
                    'institution_id' => $institution->id,
                    'orphan_class_id' => $class?->id,
                    'class_level' => $classLevel,
                    'school_fee' => $fee,
                    'fee_frequency' => 'termly',
                    'is_fee_supported' => true,
                    'support_amount' => $support,
                    'is_current' => true,
                    'started_at' => now()->subMonths(8)->toDateString(),
                ]
            );
        }
    }

    protected function seedHealthcare(): void
    {
        // [orphan reg_no, doctor, illness name, lab cost, drug cost, status]
        $prescriptions = [
            ['UAT-ORP-001', 'Dr. Sani Musa', 'Malaria', 2000, 3500, PrescriptionStatus::TREATED],
            ['UAT-ORP-002', 'Dr. Sani Musa', 'Typhoid Fever', 3500, 5000, PrescriptionStatus::TREATED],
            ['UAT-ORP-004', 'Dr. Zainab Adamu', 'Malaria', 2000, 3000, PrescriptionStatus::TREATED],
            ['UAT-ORP-005', 'Dr. Zainab Adamu', 'Anemia', 2500, 4500, PrescriptionStatus::PENDING],
            ['UAT-ORP-010', 'Dr. Sani Musa', 'Upper Respiratory Tract Infection', 1500, 2500, PrescriptionStatus::TREATED],
            ['UAT-ORP-013', 'Dr. Zainab Adamu', 'Asthma', 3000, 6000, PrescriptionStatus::PENDING],
        ];

        foreach ($prescriptions as [$regNo, $doctor, $illness, $labCost, $drugCost, $status]) {
            $orphan = Orphan::where('reg_no', $regNo)->first();
            if (! $orphan) {
                continue;
            }

            Prescription::firstOrCreate(
                [
                    'prescribable_type' => Orphan::class,
                    'prescribable_id' => $orphan->id,
                    'doctor_name' => $doctor,
                    'illness' => $illness,
                    // Pass a Carbon instance, not a date string: Eloquent's
                    // date-cast comparison then matches the stored datetime
                    // (a bare "Y-m-d" string never matches "Y-m-d H:i:s").
                    'prescription_date' => \Carbon\Carbon::parse(self::ANCHOR_DATE)->subDays(30),
                ],
                [
                    'lab_test_cost' => $labCost,
                    'drug_cost' => $drugCost,
                    'user_id' => $this->admin->id,
                    'status' => $status,
                    'treated_at' => $status === PrescriptionStatus::TREATED ? \Carbon\Carbon::parse(self::ANCHOR_DATE)->subDays(20) : null,
                ]
            );
        }
    }

    protected function seedInterventions(): void
    {
        // [orphan reg_no, intervention type name, status, amount]
        $requests = [
            ['UAT-ORP-001', 'Education - School Fees', InterventionStatus::FULFILLED, 15000],
            ['UAT-ORP-002', 'Education - Uniform & Books', InterventionStatus::FULFILLED, 8000],
            ['UAT-ORP-003', 'Education - School Fees', InterventionStatus::APPROVED, 12000],
            ['UAT-ORP-004', 'Education - Tuition Support', InterventionStatus::FULFILLED, 20000],
            ['UAT-ORP-005', 'Healthcare Support', InterventionStatus::FULFILLED, 7000],
            ['UAT-ORP-010', 'Education - School Fees', InterventionStatus::PENDING, 12000],
            ['UAT-ORP-013', 'Education - Examination Fees', InterventionStatus::UNDER_REVIEW, 5000],
        ];

        foreach ($requests as [$regNo, $typeName, $status, $amount]) {
            $orphan = Orphan::where('reg_no', $regNo)->first();
            $type = InterventionType::where('name', $typeName)->first();
            if (! $orphan || ! $type) {
                continue;
            }

            InterventionRequest::firstOrCreate(
                [
                    'orphan_id' => $orphan->id,
                    'intervention_type_id' => $type->id,
                    // Carbon instance (see prescription note above).
                    'request_date' => \Carbon\Carbon::parse(self::ANCHOR_DATE)->subMonths(2),
                ],
                [
                    'status' => $status,
                    'requested_amount' => $amount,
                    'notes' => 'UAT deterministic intervention request',
                ]
            );
        }
    }
}
