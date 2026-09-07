<?php

namespace Tests\Feature\Biometrics;

use App\Filament\RelationManagers\FingerprintsRelationManager;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BiometricRelationManagerUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $demoObserver;

    protected User $coordinator;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Widow $widowA;

    protected Orphan $orphanA;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // 1. Admin
        $this->admin = User::factory()->create(['email' => 'admin_ui@gofmis.local']);
        $this->admin->assignRole('admin');

        // 2. Demo Observer
        $this->demoObserver = User::factory()->create(['email' => 'demo_ui@gofmis.local']);
        $this->demoObserver->assignRole('demo_observer');

        // 3. Coordinator for Zone A
        $this->coordinator = User::factory()->create(['email' => 'coord_ui@gofmis.local']);
        $this->coordinator->assignRole('coordinator');

        $this->zoneA = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone A', 'code' => 'ZA', 'coordinator_id' => $this->coordinator->id]);
        $this->zoneB = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone B', 'code' => 'ZB']);

        $deceasedId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('deceased')->insert([
            'id' => $deceasedId,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'nin' => '1234567890123',
            'reg_no' => 'REG-1001',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '1234567890',
            'vulnerability_status' => 'A',
            'date_registered' => now()->toDateString(),
            'zone_id' => $this->zoneA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widowId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('widows')->insert([
            'id' => $widowId,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'nin' => '1234567890223',
            'reg_no' => 'REG-1002',
            'is_eligible' => true,
            'is_married' => false,
            'deceased_id' => $deceasedId,
            'child_sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->widowA = Widow::find($widowId);

        $orphanId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('orphans')->insert([
            'id' => $orphanId,
            'first_name' => 'Jimmy',
            'last_name' => 'Doe',
            'gender' => 'MALE',
            'reg_no' => 'REG-1003',
            'is_eligible' => true,
            'deceased_id' => $deceasedId,
            'child_sequence' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->orphanA = Orphan::find($orphanId);
    }

    public function test_fingerprints_relation_manager_renders_for_widow_record_without_exception()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->assertSuccessful()
            ->assertSee('Biometric Fingerprints');
    }

    public function test_fingerprints_relation_manager_renders_for_orphan_record_without_exception()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->orphanA,
            'pageClass' => \App\Filament\Resources\Orphans\Pages\ViewOrphan::class,
        ])
            ->assertSuccessful()
            ->assertSee('Biometric Fingerprints');
    }

    public function test_authorized_admin_can_see_enroll_fingerprint_action()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->assertTableActionExists('enroll')
            ->assertTableActionVisible('enroll');
    }

    public function test_enroll_action_modal_can_be_mounted_without_class_resolution_error()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->mountTableAction('enroll')
            ->assertTableActionMounted('enroll');
    }

    public function test_revoke_action_renders_for_an_active_fingerprint()
    {
        $this->actingAs($this->admin);

        $print = $this->widowA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'mock_enc_temp',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->assertTableActionExists('revoke')
            ->assertTableActionVisible('revoke', $print);
    }

    public function test_demo_observer_can_render_relation_manager_but_cannot_see_mutation_actions()
    {
        $this->actingAs($this->demoObserver);

        $print = $this->widowA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'mock_enc_temp',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->assertSuccessful()
            ->assertTableActionHidden('enroll')
            ->assertTableActionHidden('revoke', $print);
    }

    public function test_coordinator_zone_isolation_behavior_remains_intact()
    {
        $this->actingAs($this->coordinator);

        // Coordinator for Zone A can access Zone A widow relation manager
        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->assertSuccessful()
            ->assertTableActionVisible('enroll');
    }

    public function test_widow_fingerprint_enrollment_submission_executes_action_successfully()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->callTableAction('enroll', data: [
                'finger_position' => 'right_thumb',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(1, $this->widowA->fingerprints()->where('is_active', true)->count());
        $this->assertEquals('right_thumb', $this->widowA->fingerprints()->first()->finger_position);
    }

    public function test_orphan_fingerprint_enrollment_submission_executes_action_successfully()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->orphanA,
            'pageClass' => \App\Filament\Resources\Orphans\Pages\ViewOrphan::class,
        ])
            ->callTableAction('enroll', data: [
                'finger_position' => 'left_index',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(1, $this->orphanA->fingerprints()->where('is_active', true)->count());
        $this->assertEquals('left_index', $this->orphanA->fingerprints()->first()->finger_position);
    }

    public function test_duplicate_active_finger_submission_fails_validation_without_creating_second_record()
    {
        $this->actingAs($this->admin);

        // Enroll right_thumb once
        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->callTableAction('enroll', data: [
                'finger_position' => 'right_thumb',
            ])
            ->assertHasNoTableActionErrors();

        // Attempt right_thumb again -> validation error
        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->callTableAction('enroll', data: [
                'finger_position' => 'right_thumb',
            ])
            ->assertHasTableActionErrors(['finger_position']);

        $this->assertEquals(1, $this->widowA->fingerprints()->count());
    }

    public function test_different_finger_positions_can_be_enrolled_for_same_beneficiary()
    {
        $this->actingAs($this->admin);

        Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->callTableAction('enroll', data: [
                'finger_position' => 'right_thumb',
            ])
            ->assertHasNoTableActionErrors()
            ->callTableAction('enroll', data: [
                'finger_position' => 'left_index',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(2, $this->widowA->fingerprints()->where('is_active', true)->count());
    }

    public function test_revoked_finger_can_be_re_enrolled_preserving_historical_record()
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widowA,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ]);

        // 1. Enroll right_thumb
        $component->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        $print = $this->widowA->fingerprints()->first();

        // 2. Revoke right_thumb
        $component->callTableAction('revoke', record: $print, data: ['revocation_reason' => 'Scar on thumb'])
            ->assertHasNoTableActionErrors();

        $this->assertFalse($print->fresh()->is_active);

        // 3. Re-enroll right_thumb
        $component->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        // Historical record preserved + new active record created
        $this->assertEquals(2, $this->widowA->fingerprints()->count());
        $this->assertEquals(1, $this->widowA->fingerprints()->where('is_active', true)->count());
        $this->assertEquals(1, $this->widowA->fingerprints()->where('is_active', false)->count());
    }
}
