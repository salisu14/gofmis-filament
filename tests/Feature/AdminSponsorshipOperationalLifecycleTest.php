<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\InstitutionType;
use App\Enums\OrphanStatus;
use App\Enums\SponsorType;
use App\Filament\Resources\Sponsors\Pages\CreateSponsor;
use App\Filament\Resources\Sponsors\Pages\EditSponsor;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Filament\Resources\Sponsorships\Pages\CreateSponsorship;
use App\Filament\Resources\Sponsorships\Pages\EditSponsorship;
use App\Filament\Resources\Sponsorships\Pages\ListSponsorships;
use App\Filament\Resources\Sponsorships\Pages\ViewSponsorship;
use App\Filament\Resources\Sponsorships\SponsorshipResource;
use App\Models\Deceased;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorshipAllocation;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSponsorshipOperationalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Zone $zone;

    protected Deceased $deceased;

    protected Orphan $eligibleOrphan;

    protected Sponsor $sponsor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole('admin');

        $this->zone = Zone::create([
            'name' => 'Central Zone',
            'code' => 'CZ01',
        ]);

        $this->deceased = Deceased::factory()->create([
            'zone_id' => $this->zone->id,
        ]);

        $this->eligibleOrphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'reg_no' => 'ORP-TEST-001',
            'child_sequence' => 1,
            'first_name' => 'Ali',
            'last_name' => 'Doe',
            'gender' => Gender::MALE,
            'birth_date' => '2015-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->sponsor = Sponsor::create([
            'name' => 'Al-Hikmah Foundation',
            'type' => SponsorType::NGO,
            'email' => 'contact@alhikmah.org',
            'phone' => '+2348000000000',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_render_sponsor_pages(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListSponsors::class)->assertSuccessful();
        Livewire::test(CreateSponsor::class)->assertSuccessful();
        Livewire::test(EditSponsor::class, ['record' => $this->sponsor->getKey()])->assertSuccessful();
    }

    public function test_admin_can_render_sponsorship_pages(): void
    {
        $this->actingAs($this->admin);

        $sponsorship = Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 150000,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
        ]);

        Livewire::test(ListSponsorships::class)->assertSuccessful();
        Livewire::test(CreateSponsorship::class)->assertSuccessful();
        Livewire::test(ViewSponsorship::class, ['record' => $sponsorship->getKey()])->assertSuccessful();
        Livewire::test(EditSponsorship::class, ['record' => $sponsorship->getKey()])->assertSuccessful();
    }

    public function test_valid_sponsorship_creation_succeeds(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateSponsorship::class)
            ->set('data.orphan_id', $this->eligibleOrphan->id)
            ->set('data.sponsor_id', $this->sponsor->id)
            ->set('data.amount_committed', 200000)
            ->set('data.start_date', now()->format('Y-m-d'))
            ->set('data.end_date', now()->addMonths(6)->format('Y-m-d'))
            ->set('data.notes', 'Educational support package')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sponsorships', [
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 200000.00,
        ]);
    }

    public function test_ineligible_or_archived_orphan_cannot_receive_new_sponsorship(): void
    {
        $this->actingAs($this->admin);

        $otherDeceased = Deceased::factory()->create([
            'zone_id' => $this->zone->id,
        ]);

        $archivedOrphan = Orphan::create([
            'deceased_id' => $otherDeceased->id,
            'reg_no' => 'ORP-TEST-002',
            'child_sequence' => 1,
            'first_name' => 'Musa',
            'last_name' => 'Doe',
            'gender' => Gender::MALE,
            'birth_date' => '2005-01-01',
            'status' => OrphanStatus::ARCHIVED,
            'is_eligible' => false,
        ]);

        $this->expectException(ValidationException::class);

        Sponsorship::create([
            'orphan_id' => $archivedOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now(),
        ]);
    }

    public function test_duplicate_active_sponsorship_is_rejected_cleanly(): void
    {
        $this->actingAs($this->admin);

        Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(6),
        ]);

        $anotherSponsor = Sponsor::create([
            'name' => 'Hope Global',
            'type' => SponsorType::Corporate,
        ]);

        $this->expectException(ValidationException::class);

        Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $anotherSponsor->id,
            'amount_committed' => 120000,
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->expectException(ValidationException::class);

        Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now(),
            'end_date' => now()->subDays(10),
        ]);
    }

    public function test_expired_historical_sponsorship_does_not_block_new_legitimate_sponsorship(): void
    {
        $this->actingAs($this->admin);

        Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
        ]);

        $newSponsorship = Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 150000,
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);

        $this->assertNotNull($newSponsorship->id);
        $this->assertTrue($this->eligibleOrphan->hasActiveSponsorship());
    }

    public function test_expired_sponsorship_cannot_be_edited_and_blocks_direct_url_access(): void
    {
        $this->actingAs($this->admin);

        $expiredSponsorship = Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
        ]);

        $this->assertFalse(SponsorshipResource::canEdit($expiredSponsorship));

        Livewire::test(EditSponsorship::class, ['record' => $expiredSponsorship->getKey()])
            ->assertStatus(403);
    }

    public function test_deleting_sponsor_with_historical_sponsorships_is_protected(): void
    {
        $this->actingAs($this->admin);

        Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now(),
        ]);

        $this->expectException(\DomainException::class);

        $this->sponsor->delete();
    }

    public function test_deleting_sponsorship_with_allocations_is_protected(): void
    {
        $this->actingAs($this->admin);

        $institution = Institution::create([
            'name' => 'Central Academy',
            'type' => InstitutionType::WESTERN,
            'contact_person' => 'Principal',
            'phone' => '+2348000000001',
        ]);

        $education = OrphanEducation::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'institution_id' => $institution->id,
            'level' => 'Primary 4',
            'annual_fee' => 100000,
        ]);

        $sponsorship = Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now(),
        ]);

        SponsorshipAllocation::create([
            'sponsorship_id' => $sponsorship->id,
            'sponsor_id' => $this->sponsor->id,
            'orphan_education_id' => $education->id,
            'amount_allocated' => 50000,
        ]);

        $this->assertFalse(SponsorshipResource::canDelete($sponsorship));

        $this->expectException(\DomainException::class);

        $sponsorship->delete();
    }

    public function test_orphan_has_active_sponsorship_reflects_realtime_status(): void
    {
        $this->assertFalse($this->eligibleOrphan->hasActiveSponsorship());

        $sponsorship = Sponsorship::create([
            'orphan_id' => $this->eligibleOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonths(6),
        ]);

        $this->assertTrue($this->eligibleOrphan->fresh()->hasActiveSponsorship());

        $sponsorship->update(['end_date' => now()->subDay()]);

        $this->assertFalse($this->eligibleOrphan->fresh()->hasActiveSponsorship());
    }
}
