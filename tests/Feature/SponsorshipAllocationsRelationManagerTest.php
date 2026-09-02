<?php

namespace Tests\Feature;

use App\Models\Deceased;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorshipAllocation;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SponsorshipAllocationsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Zone $zone;

    protected Deceased $deceased;

    protected Orphan $orphan;

    protected Institution $institution;

    protected OrphanEducation $education;

    protected Sponsor $sponsor;

    protected Sponsorship $sponsorshipA;

    protected Sponsorship $sponsorshipB;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->zone = Zone::create(['name' => 'Kano Central Zone', 'code' => 'KCZ']);

        $this->deceased = Deceased::create([
            'first_name' => 'Deceased',
            'last_name' => 'Parent',
            'nin' => '12345678901',
            'reg_no' => 'DEC-KCZ-001',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'vulnerability_status' => 'A',
            'date_registered' => now(),
            'zone_id' => $this->zone->id,
        ]);

        $this->orphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'first_name' => 'Orphan',
            'last_name' => 'Student',
            'date_of_birth' => now()->subYears(10),
            'gender' => \App\Enums\Gender::MALE->value,
            'reg_no' => 'ORP-KCZ-001',
            'is_eligible' => true,
            'status' => 'active',
        ]);

        $this->institution = Institution::create([
            'name' => 'Kano Model School',
            'code' => 'KMS-01',
            'type' => \App\Enums\InstitutionType::WESTERN->value,
        ]);

        $this->education = OrphanEducation::create([
            'orphan_id' => $this->orphan->id,
            'institution_id' => $this->institution->id,
            'education_level' => 'primary',
            'class_level' => 'Primary 4',
            'academic_year' => '2025/2026',
        ]);

        $this->sponsor = Sponsor::create([
            'name' => 'Alhaji Danladi',
            'phone' => '08033333333',
            'email' => 'danladi@example.com',
            'type' => 'individual',
            'status' => 'active',
        ]);

        $this->sponsorshipA = Sponsorship::create([
            'sponsor_id' => $this->sponsor->id,
            'orphan_id' => $this->orphan->id,
            'amount_committed' => 100000.00,
            'start_date' => now()->startOfYear(),
            'academic_year' => '2025/2026',
            'status' => 'active',
        ]);

        $deceasedB = Deceased::create([
            'first_name' => 'Deceased Two',
            'last_name' => 'Parent',
            'nin' => '12345678902',
            'reg_no' => 'DEC-KCZ-002',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'vulnerability_status' => 'A',
            'date_registered' => now(),
            'zone_id' => $this->zone->id,
        ]);

        $orphanB = Orphan::create([
            'deceased_id' => $deceasedB->id,
            'first_name' => 'Orphan Two',
            'last_name' => 'Student',
            'date_of_birth' => now()->subYears(10),
            'gender' => \App\Enums\Gender::MALE->value,
            'reg_no' => 'ORP-KCZ-002',
            'is_eligible' => true,
            'status' => 'active',
        ]);

        $this->sponsorshipB = Sponsorship::create([
            'sponsor_id' => $this->sponsor->id,
            'orphan_id' => $orphanB->id,
            'amount_committed' => 50000.00,
            'start_date' => now()->startOfYear(),
            'academic_year' => '2025/2026',
            'status' => 'active',
        ]);
    }

    public function test_1_relation_manager_does_not_declare_related_resource(): void
    {
        $relationManager = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA,
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ]);

        $instance = $relationManager->instance();
        expect($instance->getRelatedResource())->toBeNull();
    }

    public function test_2_allocation_created_under_sponsorship_a_automatically_associates_with_sponsorship_a(): void
    {
        $allocation = SponsorshipAllocation::create([
            'sponsorship_id' => $this->sponsorshipA->id,
            'orphan_education_id' => $this->education->id,
            'amount_allocated' => 40000.00,
            'status' => 'active',
        ]);

        expect($allocation->sponsorship_id)->toBe($this->sponsorshipA->id);
    }

    public function test_3_livewire_form_schema_contains_allocation_fields_not_sponsorship_fields(): void
    {
        $relationManager = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA,
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->assertSuccessful();

        $instance = $relationManager->instance();
        $formSchema = $instance->form(\Filament\Schemas\Schema::make($instance));
        $componentNames = collect($formSchema->getFlatComponents())
            ->map(fn ($c) => method_exists($c, 'getName') ? $c->getName() : null)
            ->filter()
            ->values()
            ->toArray();

        expect($componentNames)->toContain('orphan_education_id');
        expect($componentNames)->toContain('amount_allocated');
        expect($componentNames)->not->toContain('sponsor_id');
    }

    public function test_4_valid_create_table_action_creates_allocation_linked_to_sponsorship(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA,
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->callTableAction('create', data: [
                'orphan_education_id' => $this->education->id,
                'amount_allocated' => 30000.00,
            ])
            ->assertHasNoTableActionErrors();

        expect($this->sponsorshipA->allocations()->count())->toBe(1);

        $allocation = $this->sponsorshipA->allocations()->first();
        expect($allocation->orphan_education_id)->toBe($this->education->id);
        expect($allocation->amount_allocated)->toEqual(30000.00);
        expect($allocation->sponsor_id)->toBe($this->sponsor->id);
    }

    public function test_5_multiple_allocations_aggregate_correctly_up_to_committed_amount(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA,
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->callTableAction('create', data: [
                'orphan_education_id' => $this->education->id,
                'amount_allocated' => 40000.00,
            ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA->fresh(),
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->callTableAction('create', data: [
                'orphan_education_id' => $this->education->id,
                'amount_allocated' => 60000.00,
            ]);

        expect($this->sponsorshipA->allocations()->sum('amount_allocated'))->toEqual(100000.00);
    }

    public function test_6_over_allocation_exceeding_committed_amount_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        SponsorshipAllocation::create([
            'sponsorship_id' => $this->sponsorshipA->id,
            'orphan_education_id' => $this->education->id,
            'amount_allocated' => 70000.00,
        ]);

        SponsorshipAllocation::create([
            'sponsorship_id' => $this->sponsorshipA->id,
            'orphan_education_id' => $this->education->id,
            'amount_allocated' => 40000.00, // 70k + 40k = 110k (exceeds 100k committed)
        ]);
    }

    public function test_7_zero_or_negative_allocation_amount_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA,
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->callTableAction('create', data: [
                'orphan_education_id' => $this->education->id,
                'amount_allocated' => 0,
            ])
            ->assertHasTableActionErrors(['amount_allocated']);
    }

    public function test_8_edit_table_action_updates_allocation_amount_and_recipient(): void
    {
        $allocation = SponsorshipAllocation::create([
            'sponsorship_id' => $this->sponsorshipA->id,
            'orphan_education_id' => $this->education->id,
            'amount_allocated' => 30000.00,
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA->fresh(),
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->callTableAction('edit', $allocation, data: [
                'orphan_education_id' => $this->education->id,
                'amount_allocated' => 50000.00,
            ])
            ->assertHasNoTableActionErrors();

        expect($allocation->fresh()->amount_allocated)->toEqual(50000.00);
    }

    public function test_9_delete_table_action_removes_allocation(): void
    {
        $allocation = SponsorshipAllocation::create([
            'sponsorship_id' => $this->sponsorshipA->id,
            'orphan_education_id' => $this->education->id,
            'amount_allocated' => 30000.00,
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Sponsorships\RelationManagers\AllocationsRelationManager::class, [
                'ownerRecord' => $this->sponsorshipA->fresh(),
                'pageClass' => \App\Filament\Resources\Sponsorships\Pages\EditSponsorship::class,
            ])
            ->callTableAction('delete', $allocation);

        expect(SponsorshipAllocation::find($allocation->id))->toBeNull();
    }

    public function test_10_deleting_sponsorship_with_allocations_throws_domain_exception(): void
    {
        SponsorshipAllocation::create([
            'sponsorship_id' => $this->sponsorshipA->id,
            'orphan_education_id' => $this->education->id,
            'amount_allocated' => 30000.00,
        ]);

        $this->expectException(\DomainException::class);
        $this->sponsorshipA->delete();
    }
}
