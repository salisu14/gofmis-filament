<?php

use App\Models\Deceased;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'Zone E']);
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Student', 'last_name' => 'One', 'full_name' => 'Student One',
        'reg_no' => 'ORP-E-1', 'nin' => '88888888888',
        'gender' => \App\Enums\Gender::FEMALE, 'birth_date' => now()->subYears(10)->toDateString(),
        'status' => \App\Enums\OrphanStatus::ACTIVE, 'is_eligible' => true,
    ]);

    $this->institution = Institution::firstOrCreate(['name' => 'Unity Academy'], ['type' => 'western']);
    $this->p4 = OrphanClass::firstOrCreate(['name' => 'Primary 4'], ['user_id' => $this->admin->id]);
    $this->p5 = OrphanClass::firstOrCreate(['name' => 'Primary 5'], ['user_id' => $this->admin->id]);

    $this->education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->institution->id,
        'orphan_class_id' => $this->p4->id,
        'class_level' => 'Primary 4',
        'is_current' => true,
        'started_at' => now()->subMonths(6)->toDateString(),
    ]);
});

/** Mirrors OrphanEducationTable's progression action semantics. */
function progressTo(OrphanEducation $record, OrphanClass $newClass, string $effectiveDate): void
{
    $record->update([
        'is_current' => false,
        'ended_at' => \Carbon\Carbon::parse($effectiveDate)->subDay()->toDateString(),
    ]);

    $new = $record->replicate(['id', 'reference', 'is_current', 'started_at', 'ended_at', 'created_at', 'updated_at']);
    $new->previous_enrollment_id = $record->id;
    $new->orphan_class_id = $newClass->id;
    $new->class_level = $newClass->name;
    $new->started_at = $effectiveDate;
    $new->is_current = true;
    $new->save();
}

test('promotion Primary 4 to Primary 5 preserves the previous class history', function () {
    progressTo($this->education, $this->p5, now()->toDateString());

    $rows = OrphanEducation::where('orphan_id', $this->orphan->id)->orderBy('started_at')->get();

    expect($rows->count())->toBe(2)
        ->and($rows[0]->orphan_class_id)->toBe($this->p4->id)
        ->and($rows[0]->is_current)->toBeFalse()
        ->and($rows[0]->ended_at)->not->toBeNull()
        ->and($rows[1]->orphan_class_id)->toBe($this->p5->id)
        ->and($rows[1]->is_current)->toBeTrue()
        ->and($rows[1]->reference)->not->toBeNull();
});

test('demotion Primary 5 to Primary 4 also preserves history', function () {
    progressTo($this->education, $this->p5, now()->toDateString());
    $current = OrphanEducation::where('orphan_id', $this->orphan->id)->where('is_current', true)->first();
    progressTo($current, $this->p4, now()->addDays(30)->toDateString());

    $rows = OrphanEducation::where('orphan_id', $this->orphan->id)->orderBy('started_at')->get();

    expect($rows->count())->toBe(3)
        ->and($rows[0]->orphan_class_id)->toBe($this->p4->id)
        ->and($rows[1]->orphan_class_id)->toBe($this->p5->id)
        ->and($rows[2]->orphan_class_id)->toBe($this->p4->id)
        ->and($rows[2]->is_current)->toBeTrue()
        ->and($rows[1]->is_current)->toBeFalse();
});

test('unrelated education financial records remain intact after promotion', function () {
    // A fee invoice/support record tied to the original education row.
    $invoice = \App\Models\EducationFeeInvoice::create([
        'orphan_education_id' => $this->education->id,
        'amount' => 25000.00,
        'due_date' => now()->addDays(30)->toDateString(),
        'period' => 'Term 2',
        'issued_at' => now()->toDateString(),
    ]);

    progressTo($this->education, $this->p5, now()->toDateString());

    expect($invoice->fresh())->not->toBeNull()
        ->and((float) $invoice->fresh()->amount)->toBe(25000.0)
        ->and(OrphanEducation::where('orphan_id', $this->orphan->id)->count())->toBe(2);
});
