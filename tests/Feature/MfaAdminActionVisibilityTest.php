<?php

use App\Filament\Pages\MfaManagement;
use App\Models\User;
use App\Services\MfaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->superAdmin = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);
    $this->coordinator->assignRole('coordinator');
});

test('non-enrolled user has Force Enrollment visible and Disable MFA hidden in MfaManagement', function () {
    expect($this->coordinator->twoFactorAuthEnabled())->toBeFalse();

    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->assertTableActionVisible('requireEnrollment', $this->coordinator)
        ->assertTableActionHidden('disableMfa', $this->coordinator);
});

test('active MFA enrolled user has Force Enrollment hidden and Disable MFA visible in MfaManagement', function () {
    $this->coordinator->update([
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
        'mfa_enrollment_required' => false,
    ]);

    expect($this->coordinator->twoFactorAuthEnabled())->toBeTrue();

    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->assertTableActionHidden('requireEnrollment', $this->coordinator)
        ->assertTableActionVisible('disableMfa', $this->coordinator)
        ->assertTableActionVisible('resetMfa', $this->coordinator);
});

test('disabling MFA via admin service removes credentials without exposing secrets and updates state', function () {
    $this->coordinator->update([
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);

    $service = new MfaService;
    $service->adminDisableMfa($this->superAdmin, $this->coordinator);

    $fresh = $this->coordinator->fresh();
    expect($fresh->twoFactorAuthEnabled())->toBeFalse();
    expect($fresh->app_authentication_secret)->toBeNull();
    expect($fresh->mfa_confirmed_at)->toBeNull();
    expect($fresh->mfa_enrollment_required)->toBeFalse();
});

test('unauthorized user cannot disable MFA for another user', function () {
    $unauthorizedUser = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);
    $this->coordinator->update([
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
    ]);

    $service = new MfaService;
    expect(fn () => $service->adminDisableMfa($unauthorizedUser, $this->coordinator))
        ->toThrow(ValidationException::class);
});

test('reset MFA behavior remains distinct from disable MFA by enforcing re-enrollment requirement for mandatory roles', function () {
    $this->admin->update([
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);

    expect($this->admin->isMfaRequired())->toBeTrue();

    $service = new MfaService;
    $service->resetMfa($this->superAdmin, $this->admin);

    $fresh = $this->admin->fresh();
    expect($fresh->twoFactorAuthEnabled())->toBeFalse();
    expect($fresh->app_authentication_secret)->toBeNull();
    expect((bool) $fresh->mfa_enrollment_required)->toBeTrue(); // Enforces re-enrollment requirement
});

test('force enrollment action works for non-enrolled user and hides force enrollment while showing remove forced enrollment', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->assertTableActionVisible('requireEnrollment', $this->coordinator)
        ->assertTableActionHidden('removeEnrollmentRequirement', $this->coordinator);

    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->callTableAction('requireEnrollment', $this->coordinator);

    expect((bool) $this->coordinator->fresh()->mfa_enrollment_required)->toBeTrue();

    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->assertTableActionHidden('requireEnrollment', $this->coordinator)
        ->assertTableActionVisible('removeEnrollmentRequirement', $this->coordinator);
});
