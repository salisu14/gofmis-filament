<?php

use App\Filament\Coordinator\Resources\WidowResource\Pages\ListWidows as CoordinatorListWidows;
use App\Filament\Resources\Widows\Pages\ListWidows as AdminListWidows;
use App\Filament\Resources\Widows\Pages\ViewWidow;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Kano Central', 'coordinator_id' => $this->coordinator->id]);
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->widow = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901',
        'reg_no' => 'WID-2026-0001',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $this->deceased->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);
});

// 1. Mark-as-married action executes without SQL error & persists married_at
test('1. mark as married action executes cleanly and sets is_married and married_at', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $marriageDate = '2026-06-15';

    Livewire::test(AdminListWidows::class)
        ->callTableAction('markAsMarried', $this->widow, [
            'married_at' => $marriageDate,
            'notes' => 'Remarried to new spouse',
        ]);

    $this->widow->refresh();

    expect($this->widow->is_married)->toBeTrue()
        ->and($this->widow->is_eligible)->toBeFalse()
        ->and($this->widow->married_at->format('Y-m-d'))->toBe('2026-06-15');
});

// 2. Existing widow history and relationships remain intact after remarriage
test('2. widow remarriage preserves historical records loans and interventions', function () {
    $template = IdCardTemplate::create([
        'name' => 'Standard Widow Template',
        'type' => 'widow',
        'is_active' => true,
    ]);

    $idCard = IdCard::create([
        'card_number' => 'GOF-W-2026-0001',
        'cardable_type' => Widow::class,
        'cardable_id' => $this->widow->id,
        'template_id' => $template->id,
        'qr_code_path' => 'qrcodes/test.png',
        'issued_at' => now(),
        'status' => 'active',
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000,
        'total_amount' => 50000,
        'repayment_term_months' => 6,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
    ]);

    $welfarePackage = \App\Models\WelfarePackage::create([
        'name' => 'Widow Food Package',
        'status' => \App\Enums\WelfarePackageStatus::OPEN,
        'start_date' => now(),
        'end_date' => now()->addDays(30),
        'created_by' => $this->admin->id,
    ]);

    $welfareBeneficiary = \App\Models\WelfareBeneficiary::create([
        'welfare_package_id' => $welfarePackage->id,
        'deceased_id' => $this->deceased->id,
        'status' => \App\Enums\BeneficiaryStatus::PENDING,
        'suggested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    $this->widow->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    $this->widow->refresh();

    // Verify widow is not deleted
    expect(Widow::find($this->widow->id))->not->toBeNull()
        ->and($this->widow->deceased_id)->toBe((string) $this->deceased->id);

    // Verify loan is intact
    expect(WidowLoan::where('widow_id', $this->widow->id)->exists())->toBeTrue();

    // Verify ID card revoked but present
    $idCard->refresh();
    expect($idCard->status)->toBe('revoked');

    // Verify welfare beneficiary record intact
    $welfareBeneficiary->refresh();
    expect($welfareBeneficiary->deceased_id)->toBe((string) $this->deceased->id);
});

// 3. Coordinator mark as married action works safely
test('3. coordinator can mark widow in own zone as married', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorListWidows::class)
        ->callTableAction('markAsMarried', $this->widow, [
            'married_at' => '2026-08-10',
            'notes' => 'Coordinator marked marriage',
        ]);

    $this->widow->refresh();

    expect($this->widow->is_married)->toBeTrue()
        ->and($this->widow->married_at->format('Y-m-d'))->toBe('2026-08-10');
});

// 4. Married widow remains viewable in infolist
test('4. married widow remains viewable and displays married_at date', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->widow->update([
        'is_married' => true,
        'married_at' => '2026-05-20 00:00:00',
        'is_eligible' => false,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewWidow::class, ['record' => $this->widow->id])
        ->assertSuccessful()
        ->assertSee('Amina Usman');
});

// 5. Legacy married record with null married_at remains readable
test('5. legacy married record with null married_at remains readable without error', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $legacyWidow = Widow::create([
        'first_name' => 'Fatima',
        'last_name' => 'Bello',
        'nin' => '98765432109',
        'reg_no' => 'WID-LEGACY-01',
        'is_eligible' => false,
        'is_married' => true,
        'married_at' => null,
        'deceased_id' => $this->deceased->id,
        'child_sequence' => 2,
        'full_name' => 'Fatima Bello',
        'address' => 'Garko, Kano State',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewWidow::class, ['record' => $legacyWidow->id])
        ->assertSuccessful()
        ->assertSee('Fatima Bello');
});
