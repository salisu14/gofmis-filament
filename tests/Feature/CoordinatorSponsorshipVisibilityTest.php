<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\SponsorType;
use App\Filament\Coordinator\Resources\OrphanResource\Pages\ListOrphans;
use App\Filament\Coordinator\Resources\OrphanResource\Pages\ViewOrphan;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CoordinatorSponsorshipVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Deceased $deceasedZoneA;

    protected Deceased $deceasedZoneB;

    protected Orphan $orphanZoneA;

    protected Orphan $orphanZoneB;

    protected Sponsor $sponsor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->coordinatorZoneA = User::factory()->create([
            'email' => 'coord_a@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorZoneA->assignRole('coordinator');

        $this->coordinatorZoneB = User::factory()->create([
            'email' => 'coord_b@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorZoneB->assignRole('coordinator');

        $this->zoneA = Zone::create([
            'name' => 'Zone A',
            'code' => 'ZA01',
            'coordinator_id' => $this->coordinatorZoneA->id,
        ]);

        $this->zoneB = Zone::create([
            'name' => 'Zone B',
            'code' => 'ZB01',
            'coordinator_id' => $this->coordinatorZoneB->id,
        ]);

        $this->deceasedZoneA = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
        ]);

        $this->deceasedZoneB = Deceased::factory()->create([
            'zone_id' => $this->zoneB->id,
        ]);

        $this->orphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-ZA-01',
            'child_sequence' => 1,
            'first_name' => 'Farouk',
            'last_name' => 'ZoneA',
            'gender' => Gender::MALE,
            'birth_date' => '2016-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->orphanZoneB = Orphan::create([
            'deceased_id' => $this->deceasedZoneB->id,
            'reg_no' => 'ORP-ZB-01',
            'child_sequence' => 1,
            'first_name' => 'Habib',
            'last_name' => 'ZoneB',
            'gender' => Gender::MALE,
            'birth_date' => '2017-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->sponsor = Sponsor::create([
            'name' => 'Crescent Aid',
            'type' => SponsorType::NGO,
            'email' => 'private_email@crescentaid.org',
            'phone' => '+2349000000000',
            'address' => 'Private Sponsor HQ',
        ]);

        Sponsorship::create([
            'orphan_id' => $this->orphanZoneA->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 180000,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'notes' => 'Annual educational grant',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    }

    public function test_coordinator_cannot_access_admin_sponsor_master_resources(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $response = $this->get('/admin/donors');
        $response->assertStatus(403);

        $response2 = $this->get('/admin/sponsorships');
        $response2->assertStatus(403);
    }

    public function test_coordinator_orphans_table_renders_with_sponsorship_indicator(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(ListOrphans::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->orphanZoneA])
            ->assertCanNotSeeTableRecords([$this->orphanZoneB]);

        $this->assertTrue($this->orphanZoneA->hasActiveSponsorship());
        $this->assertFalse($this->orphanZoneB->hasActiveSponsorship());
    }

    public function test_coordinator_orphan_view_displays_active_sponsorship_summary(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(ViewOrphan::class, ['record' => $this->orphanZoneA->getKey()])
            ->assertSuccessful()
            ->assertSee('Sponsorship Overview')
            ->assertSee('Crescent Aid')
            ->assertSee('180,000')
            ->assertSee('Annual educational grant')
            ->assertDontSee('private_email@crescentaid.org')
            ->assertDontSee('Private Sponsor HQ');
    }

    public function test_unsponsored_orphan_view_displays_not_sponsored(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $unsponsoredOrphan = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-ZA-02',
            'child_sequence' => 2,
            'first_name' => 'Usman',
            'last_name' => 'ZoneA',
            'gender' => Gender::MALE,
            'birth_date' => '2018-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        Livewire::test(ViewOrphan::class, ['record' => $unsponsoredOrphan->getKey()])
            ->assertSuccessful()
            ->assertSee('Sponsorship Overview')
            ->assertSee('Not Sponsored');
    }

    public function test_expired_sponsorship_is_not_shown_as_active(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $pastOrphan = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-ZA-03',
            'child_sequence' => 3,
            'first_name' => 'Khadija',
            'last_name' => 'ZoneA',
            'gender' => Gender::FEMALE,
            'birth_date' => '2019-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        Sponsorship::create([
            'orphan_id' => $pastOrphan->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 100000,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
        ]);

        $this->assertFalse($pastOrphan->hasActiveSponsorship());

        Livewire::test(ViewOrphan::class, ['record' => $pastOrphan->getKey()])
            ->assertSuccessful()
            ->assertSee('Historical Sponsorships')
            ->assertSee('Past Sponsorship Records');
    }

    public function test_zone_isolation_prevents_coordinator_from_seeing_other_zone_orphans(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $visibleOrphanIds = Orphan::pluck('id')->toArray();

        $this->assertContains($this->orphanZoneA->id, $visibleOrphanIds);
        $this->assertNotContains($this->orphanZoneB->id, $visibleOrphanIds);
    }

    public function test_archived_orphan_cannot_receive_new_sponsorship(): void
    {
        $archivedOrphan = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-ZA-04',
            'child_sequence' => 4,
            'first_name' => 'Bashir',
            'last_name' => 'ZoneA',
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
}
