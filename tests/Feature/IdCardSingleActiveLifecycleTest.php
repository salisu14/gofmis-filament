<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Filament\Resources\IdCards\Pages\ViewIdCard;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\IdCardGenerationService;
use App\Services\QRCodeService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IdCardSingleActiveLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Zone $zone;

    protected Deceased $deceased;

    protected Orphan $orphan;

    protected Widow $widow;

    protected IdCardTemplate $orphanTemplate;

    protected IdCardTemplate $widowTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->zone = Zone::create([
            'name' => 'Test Zone',
            'code' => 'TZONE',
        ]);

        $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

        $this->orphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'reg_no' => 'ORP-SAC-01',
            'child_sequence' => 1,
            'first_name' => 'Samuel',
            'last_name' => 'Balogun',
            'gender' => Gender::MALE,
            'birth_date' => '2016-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->widow = Widow::create([
            'deceased_id' => $this->deceased->id,
            'reg_no' => 'WID-SAC-01',
            'child_sequence' => 1,
            'first_name' => 'Zainab',
            'last_name' => 'Balogun',
            'nin' => '10000000001',
            'is_eligible' => true,
            'is_married' => false,
            'address' => 'Test Address',
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

    public function test_orphan_card_replacement_prevents_reactivating_old_card_while_new_card_active(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        // 1. Initial card issuance and activation (Card A)
        $cardA = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $cardA->activate();
        $this->assertTrue($cardA->fresh()->isActive());

        // 2. Replace Card A with Card B
        $cardA->revoke('Replaced: Lost by beneficiary');
        $this->assertEquals('revoked', $cardA->fresh()->status);

        $cardB = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $cardB->activate();
        $this->assertTrue($cardB->fresh()->isActive());

        // 3. Attempting to reactivate Card A must be rejected
        $this->expectException(ValidationException::class);
        try {
            $cardA->reactivate();
        } finally {
            // 4. Verify states and invariant
            $this->assertEquals('revoked', $cardA->fresh()->status);
            $this->assertEquals('active', $cardB->fresh()->status);
            $this->assertEquals(
                1,
                IdCard::where('cardable_type', Orphan::class)
                    ->where('cardable_id', $this->orphan->id)
                    ->where('status', 'active')
                    ->count()
            );
        }
    }

    public function test_widow_card_replacement_prevents_reactivating_old_card_while_new_card_active(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        // 1. Initial card issuance and activation (Card A)
        $cardA = $genService->generateCard($this->widow, $this->widowTemplate, false);
        $cardA->activate();
        $this->assertTrue($cardA->fresh()->isActive());

        // 2. Replace Card A with Card B
        $cardA->revoke('Replaced: Stolen card');
        $cardB = $genService->generateCard($this->widow, $this->widowTemplate, false);
        $cardB->activate();

        // 3. Attempting to reactivate Card A must fail
        $this->expectException(ValidationException::class);
        try {
            $cardA->reactivate();
        } finally {
            $this->assertEquals('revoked', $cardA->fresh()->status);
            $this->assertEquals('active', $cardB->fresh()->status);
            $this->assertEquals(
                1,
                IdCard::where('cardable_type', Widow::class)
                    ->where('cardable_id', $this->widow->id)
                    ->where('status', 'active')
                    ->count()
            );
        }
    }

    public function test_ordinary_revoked_card_can_be_reactivated_when_no_competing_active_card_exists(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $card = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $card->activate();

        // Ordinarily revoked (no replacement card created)
        $card->revoke('Temporary suspension');
        $this->assertEquals('revoked', $card->fresh()->status);

        // Reactivation succeeds because no competing active card exists
        $card->reactivate();
        $this->assertEquals('active', $card->fresh()->status);
    }

    public function test_ui_hides_reactivate_action_when_another_card_is_active(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);

        $cardA = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $cardA->activate();

        $cardA->revoke('Replaced: Damaged card');
        $cardB = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $cardB->activate();

        // ViewIdCard for superseded Card A should HIDE reactivate action
        Livewire::test(ViewIdCard::class, ['record' => $cardA->getKey()])
            ->assertSuccessful()
            ->assertActionHidden('reactivate');

        // ViewIdCard for active Card B shows replace and revoke
        Livewire::test(ViewIdCard::class, ['record' => $cardB->getKey()])
            ->assertSuccessful()
            ->assertActionVisible('replace')
            ->assertActionVisible('revoke')
            ->assertActionHidden('reactivate');
    }

    public function test_qr_verification_behavior_remains_accurate(): void
    {
        $this->actingAs($this->admin);
        $genService = app(IdCardGenerationService::class);
        $qrService = app(QRCodeService::class);

        $cardA = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $cardA->activate();

        $cardA->revoke('Replaced: Lost');
        $cardB = $genService->generateCard($this->orphan, $this->orphanTemplate, false);
        $cardB->activate();

        // Revoked old card QR returns invalid
        $verifyA = $qrService->verify($cardA->id);
        $this->assertFalse($verifyA['valid']);
        $this->assertStringContainsString('revoked', strtolower($verifyA['message']));

        // Active replacement card QR returns valid
        $verifyB = $qrService->verify($cardB->id);
        $this->assertTrue($verifyB['valid']);
        $this->assertEquals('Samuel Balogun', $verifyB['name']);
    }
}
