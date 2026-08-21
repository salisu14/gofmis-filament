<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Filament\Coordinator\Resources\OrphanResource\Pages\ViewOrphan as CoordinatorViewOrphan;
use App\Filament\Coordinator\Resources\WidowResource\Pages\ViewWidow as CoordinatorViewWidow;
use App\Filament\Resources\IdCards\Pages\ListIdCards;
use App\Models\Deceased;
use App\Models\IdCardTemplate;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\IdCardGenerationService;
use App\Services\IdCardPDFService;
use App\Services\QRCodeService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BeneficiaryIdCardOperationalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Deceased $deceasedZoneA;

    protected Deceased $deceasedZoneB;

    protected Orphan $activeOrphanZoneA;

    protected Orphan $archivedOrphanZoneA;

    protected Orphan $activeOrphanZoneB;

    protected Widow $activeWidowZoneA;

    protected Widow $remarriedWidowZoneA;

    protected IdCardTemplate $orphanTemplate;

    protected IdCardTemplate $widowTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin_idcards@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole('super_admin');

        $this->coordinatorZoneA = User::factory()->create([
            'email' => 'coord_a_idcards@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorZoneA->assignRole('coordinator');

        $this->coordinatorZoneB = User::factory()->create([
            'email' => 'coord_b_idcards@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorZoneB->assignRole('coordinator');

        $this->zoneA = Zone::create([
            'name' => 'Zone A ID Cards',
            'code' => 'ZAIDC',
            'coordinator_id' => $this->coordinatorZoneA->id,
        ]);

        $this->zoneB = Zone::create([
            'name' => 'Zone B ID Cards',
            'code' => 'ZBIDC',
            'coordinator_id' => $this->coordinatorZoneB->id,
        ]);

        $this->deceasedZoneA = Deceased::factory()->create(['zone_id' => $this->zoneA->id]);
        $this->deceasedZoneB = Deceased::factory()->create(['zone_id' => $this->zoneB->id]);

        $this->activeOrphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-IDC-01',
            'child_sequence' => 1,
            'first_name' => 'Kamilu',
            'last_name' => 'ZoneA',
            'gender' => Gender::MALE,
            'birth_date' => '2016-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->archivedOrphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-IDC-02',
            'child_sequence' => 2,
            'first_name' => 'Zahra',
            'last_name' => 'ZoneA',
            'gender' => Gender::FEMALE,
            'birth_date' => '2005-01-01',
            'status' => OrphanStatus::ARCHIVED,
            'is_eligible' => false,
        ]);

        $this->activeOrphanZoneB = Orphan::create([
            'deceased_id' => $this->deceasedZoneB->id,
            'reg_no' => 'ORP-IDC-03',
            'child_sequence' => 1,
            'first_name' => 'Suleiman',
            'last_name' => 'ZoneB',
            'gender' => Gender::MALE,
            'birth_date' => '2017-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->activeWidowZoneA = Widow::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'WID-IDC-01',
            'child_sequence' => 1,
            'first_name' => 'Halima',
            'last_name' => 'ZoneA',
            'nin' => '12345678901',
            'is_eligible' => true,
            'is_married' => false,
            'address' => 'Zone A Address',
        ]);

        $this->remarriedWidowZoneA = Widow::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'WID-IDC-02',
            'child_sequence' => 2,
            'first_name' => 'Safiya',
            'last_name' => 'ZoneA',
            'nin' => '12345678902',
            'is_eligible' => false,
            'is_married' => true,
            'address' => 'Zone A Address',
        ]);

        $this->orphanTemplate = IdCardTemplate::create([
            'name' => 'Standard Orphan Card',
            'type' => 'orphan',
            'is_active' => true,
            'is_default' => true,
            'layout_config' => IdCardTemplate::defaultLayoutConfig('orphan'),
        ]);

        $this->widowTemplate = IdCardTemplate::create([
            'name' => 'Standard Widow Card',
            'type' => 'widow',
            'is_active' => true,
            'is_default' => true,
            'layout_config' => IdCardTemplate::defaultLayoutConfig('widow'),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_id_card_resource_pages_render(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListIdCards::class)->assertSuccessful();
    }

    public function test_eligible_orphan_card_issuance_succeeds(): void
    {
        $this->actingAs($this->admin);

        $genService = app(IdCardGenerationService::class);
        $card = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);

        $this->assertNotNull($card);
        $this->assertEquals('draft', $card->status);
        $this->assertStringStartsWith('GOF-O-', $card->card_number);

        $card->activate();
        $this->assertEquals('active', $card->fresh()->status);
        $this->assertTrue($card->fresh()->isActive());
    }

    public function test_eligible_widow_card_issuance_succeeds(): void
    {
        $this->actingAs($this->admin);

        $genService = app(IdCardGenerationService::class);
        $card = $genService->generateCard($this->activeWidowZoneA, $this->widowTemplate, false);

        $this->assertNotNull($card);
        $this->assertEquals('draft', $card->status);
        $this->assertStringStartsWith('GOF-W-', $card->card_number);

        $card->activate();
        $this->assertTrue($card->fresh()->isActive());
    }

    public function test_ineligible_or_archived_beneficiary_issuance_rejected(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $this->expectException(ValidationException::class);
        $genService->generateCard($this->archivedOrphanZoneA, $this->orphanTemplate, false);
    }

    public function test_remarried_ineligible_widow_issuance_rejected(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $this->expectException(ValidationException::class);
        $genService->generateCard($this->remarriedWidowZoneA, $this->widowTemplate, false);
    }

    public function test_duplicate_active_or_draft_card_rejected(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $card1 = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);
        $card1->activate();

        $this->expectException(ValidationException::class);
        $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);
    }

    public function test_qr_and_public_verification_returns_expected_status(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);
        $qrService = app(QRCodeService::class);

        $card = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);

        // Draft check
        $verifyDraft = $qrService->verify($card->id);
        $this->assertFalse($verifyDraft['valid']);
        $this->assertStringContainsString('draft', strtolower($verifyDraft['message']));

        // Activate check
        $card->activate();
        $verifyActive = $qrService->verify($card->id);
        $this->assertTrue($verifyActive['valid']);
        $this->assertEquals('Kamilu ZoneA', $verifyActive['name']);

        // Revoke check
        $card->revoke('Stolen card report');
        $verifyRevoked = $qrService->verify($card->id);
        $this->assertFalse($verifyRevoked['valid']);
        $this->assertStringContainsString('revoked', strtolower($verifyRevoked['message']));
    }

    public function test_invalid_qr_token_handled_gracefully(): void
    {
        $qrService = app(QRCodeService::class);
        $res = $qrService->verify('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($res['valid']);
        $this->assertEquals('Card not found', $res['message']);
    }

    public function test_replacement_revokes_old_card_and_issues_new_active_card(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $oldCard = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);
        $oldCard->activate();

        $oldCard->revoke('Replaced: Lost by beneficiary');
        $this->assertEquals('revoked', $oldCard->fresh()->status);

        $newCard = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);
        $newCard->activate();

        $this->assertNotEquals($oldCard->card_number, $newCard->card_number);
        $this->assertEquals('active', $newCard->fresh()->status);
        $this->assertEquals('revoked', $oldCard->fresh()->status);
    }

    public function test_issued_active_revoked_card_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $card = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);
        $card->activate();

        $this->assertFalse(\App\Filament\Resources\IdCards\IdCardResource::canEdit($card));
        $this->assertFalse(\App\Filament\Resources\IdCards\IdCardResource::canDelete($card));

        $response = $this->get("/admin/id-cards/{$card->id}/edit");
        $response->assertStatus(403);
    }

    public function test_coordinator_cannot_access_admin_id_card_resource(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $response = $this->get('/admin/id-cards');
        $response->assertStatus(403);
    }

    public function test_coordinator_can_see_contextual_id_card_overview_for_own_zone(): void
    {
        $this->actingAs($this->coordinatorZoneA);
        $genService = app(IdCardGenerationService::class);

        $card = $genService->generateCard($this->activeOrphanZoneA, $this->orphanTemplate, false);
        $card->activate();

        Filament::setCurrentPanel(Filament::getPanel('coordinator'));

        Livewire::test(CoordinatorViewOrphan::class, ['record' => $this->activeOrphanZoneA->getKey()])
            ->assertSuccessful()
            ->assertSee('ID Card Overview')
            ->assertSee($card->card_number)
            ->assertSee('Valid');

        Livewire::test(CoordinatorViewWidow::class, ['record' => $this->activeWidowZoneA->getKey()])
            ->assertSuccessful()
            ->assertSee('ID Card Overview')
            ->assertSee('No ID Card Issued');
    }

    public function test_coordinator_cross_zone_id_card_access_denied(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Filament::setCurrentPanel(Filament::getPanel('coordinator'));

        $this->get("/coordinator/orphans/{$this->activeOrphanZoneB->id}")
            ->assertStatus(403);
    }

    public function test_missing_photo_degrades_gracefully_during_pdf_generation(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);
        $pdfService = app(IdCardPDFService::class);

        $orphanNoPhoto = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-IDC-99',
            'child_sequence' => 9,
            'first_name' => 'NoPhoto',
            'last_name' => 'Orphan',
            'gender' => Gender::MALE,
            'birth_date' => '2019-01-01',
            'picture_url' => null,
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $card = $genService->generateCard($orphanNoPhoto, $this->orphanTemplate, false);
        $card->activate();

        $pdf = $pdfService->generateSingle($card);
        $this->assertNotEmpty($pdf->output());
    }

    public function test_reconcile_id_cards_command_runs_cleanly(): void
    {
        $this->artisan('id-cards:reconcile --details')
            ->expectsOutputToContain('GOF MIS HISTORICAL ID CARD AUDIT REPORT')
            ->assertExitCode(0);
    }
}
