<?php

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinatorA = User::factory()->create([
        'status' => \App\Enums\UserStatus::ACTIVE,
    ]);
    $this->coordinatorA->assignRole('coordinator');

    $this->zoneA = Zone::create([
        'name' => 'Zone Alpha',
        'address' => '100 Alpha St',
        'coordinator_id' => $this->coordinatorA->id,
    ]);
    $this->coordinatorA->unsetRelation('coordinatedZone');
    $this->zoneB = Zone::create(['name' => 'Zone Beta', 'address' => '200 Beta St']);

    $this->deceasedA = Deceased::factory()->create(['zone_id' => $this->zoneA->id, 'full_name' => 'Alpha Deceased']);
    $this->deceasedB = Deceased::factory()->create(['zone_id' => $this->zoneB->id, 'full_name' => 'Beta Deceased']);

    $this->orphanA = Orphan::create([
        'deceased_id' => $this->deceasedA->id,
        'reg_no' => 'ORP-Z-A',
        'first_name' => 'OrphanA',
        'last_name' => 'Alpha',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->orphanB = Orphan::create([
        'deceased_id' => $this->deceasedB->id,
        'reg_no' => 'ORP-Z-B',
        'first_name' => 'OrphanB',
        'last_name' => 'Beta',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => '2016-01-01',
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->widowA = Widow::create([
        'deceased_id' => $this->deceasedA->id,
        'reg_no' => 'WID-Z-A',
        'first_name' => 'WidowA',
        'last_name' => 'Alpha',
        'nin' => '11111111111',
        'address' => '100 Alpha',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->widowB = Widow::create([
        'deceased_id' => $this->deceasedB->id,
        'reg_no' => 'WID-Z-B',
        'first_name' => 'WidowB',
        'last_name' => 'Beta',
        'nin' => '22222222222',
        'address' => '200 Beta',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->template = \App\Models\IdCardTemplate::create([
        'name' => 'Default Template',
        'type' => 'widow',
        'is_active' => true,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
});

test('coordinator A cannot view Zone B deceased by direct URL', function () {
    $this->actingAs($this->coordinatorA);

    $response = $this->get(\App\Filament\Coordinator\Resources\DeceasedResource::getUrl('view', ['record' => $this->deceasedB]));
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('coordinator A cannot view Zone B orphan by direct URL', function () {
    $this->actingAs($this->coordinatorA);

    $response = $this->get(\App\Filament\Coordinator\Resources\OrphanResource::getUrl('view', ['record' => $this->orphanB]));
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('coordinator A cannot view Zone B widow by direct URL', function () {
    $this->actingAs($this->coordinatorA);

    $response = $this->get(\App\Filament\Coordinator\Resources\WidowResource::getUrl('view', ['record' => $this->widowB]));
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('coordinator A can view own Zone A records by direct URL', function () {
    $this->actingAs($this->coordinatorA->fresh());
    session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->coordinatorA->id]);

    $response = $this->get(\App\Filament\Coordinator\Resources\DeceasedResource::getUrl('view', ['record' => $this->deceasedA]));
    $response->assertSuccessful();

    $responseOrphan = $this->get(\App\Filament\Coordinator\Resources\OrphanResource::getUrl('view', ['record' => $this->orphanA]));
    $responseOrphan->assertSuccessful();
});

test('coordinator A cannot download ID card belonging to Zone B beneficiary', function () {
    $this->actingAs($this->coordinatorA);
    session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->coordinatorA->id]);

    $cardB = \App\Models\IdCard::create([
        'template_id' => $this->template->id,
        'cardable_type' => Widow::class,
        'cardable_id' => $this->widowB->id,
        'card_number' => 'CARD-B-001',
        'qr_code_path' => 'id-cards/qrs/CARD-B-001.png',
        'status' => 'active',
        'issued_at' => now(),
    ]);

    $response = $this->get(route('id-cards.download', ['idCard' => $cardB->id]));
    $response->assertStatus(403);
});
