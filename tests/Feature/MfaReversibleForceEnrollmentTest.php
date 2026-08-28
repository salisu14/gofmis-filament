<?php

use App\Filament\Pages\MfaManagement;
use App\Models\User;
use App\Services\MfaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');
});

it('forces enrollment for an optional user making effective requirement required', function () {
    expect($this->coordinator->isMfaMandatoryByRole())->toBeFalse();
    expect((bool) $this->coordinator->mfa_enrollment_required)->toBeFalse();
    expect($this->coordinator->isMfaRequired())->toBeFalse();

    $service = new MfaService;
    $service->requireMfaEnrollment($this->superAdmin, $this->coordinator);

    expect((bool) $this->coordinator->fresh()->mfa_enrollment_required)->toBeTrue();
    expect($this->coordinator->fresh()->isMfaRequired())->toBeTrue();
});

it('removes forced enrollment from an optional user making requirement optional again', function () {
    $service = new MfaService;
    $service->requireMfaEnrollment($this->superAdmin, $this->coordinator);

    expect($this->coordinator->fresh()->isMfaRequired())->toBeTrue();

    $service->removeMfaEnrollmentRequirement($this->superAdmin, $this->coordinator);

    expect((bool) $this->coordinator->fresh()->mfa_enrollment_required)->toBeFalse();
    expect($this->coordinator->fresh()->isMfaRequired())->toBeFalse();
});

it('keeps mandatory-by-role user mandatory even after administrator force is removed', function () {
    // Admin role is mandatory by policy
    expect($this->admin->isMfaMandatoryByRole())->toBeTrue();

    $service = new MfaService;
    $service->requireMfaEnrollment($this->superAdmin, $this->admin);

    expect((bool) $this->admin->fresh()->mfa_enrollment_required)->toBeTrue();
    expect($this->admin->fresh()->isMfaRequired())->toBeTrue();

    // Removing admin-forced flag MUST NOT bypass role mandatory policy
    $service->removeMfaEnrollmentRequirement($this->superAdmin, $this->admin);

    expect((bool) $this->admin->fresh()->mfa_enrollment_required)->toBeFalse();
    expect($this->admin->fresh()->isMfaRequired())->toBeTrue(); // Still mandatory!
});

it('does not destroy enrolled MFA configuration when removing forced enrollment requirement', function () {
    $this->coordinator->update([
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
        'mfa_enrollment_required' => true,
    ]);

    expect($this->coordinator->twoFactorAuthEnabled())->toBeTrue();

    $service = new MfaService;
    $service->removeMfaEnrollmentRequirement($this->superAdmin, $this->coordinator);

    $fresh = $this->coordinator->fresh();
    expect((bool) $fresh->mfa_enrollment_required)->toBeFalse();
    expect($fresh->app_authentication_secret)->toBe('SECRET1234567890');
    expect($fresh->mfa_confirmed_at)->not->toBeNull();
    expect($fresh->twoFactorAuthEnabled())->toBeTrue();
});

it('prevents unauthorized actor from forcing or removing forced enrollment', function () {
    $unauthorizedUser = User::factory()->create();

    $service = new MfaService;

    expect(fn () => $service->requireMfaEnrollment($unauthorizedUser, $this->superAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => $service->removeMfaEnrollmentRequirement($unauthorizedUser, $this->superAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('renders confirmation and action visibility correctly based on forced enrollment state in MfaManagement', function () {
    // Non-forced user -> Force Enrollment is visible, Remove Forced Enrollment is hidden
    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->assertTableActionVisible('requireEnrollment', $this->coordinator)
        ->assertTableActionHidden('removeEnrollmentRequirement', $this->coordinator);

    // Call Force Enrollment action
    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->callTableAction('requireEnrollment', $this->coordinator);

    expect((bool) $this->coordinator->fresh()->mfa_enrollment_required)->toBeTrue();

    // Forced user -> Force Enrollment is hidden, Remove Forced Enrollment is visible
    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->assertTableActionHidden('requireEnrollment', $this->coordinator)
        ->assertTableActionVisible('removeEnrollmentRequirement', $this->coordinator);

    // Call Remove Forced Enrollment action
    Livewire::actingAs($this->superAdmin)
        ->test(MfaManagement::class)
        ->callTableAction('removeEnrollmentRequirement', $this->coordinator);

    expect((bool) $this->coordinator->fresh()->mfa_enrollment_required)->toBeFalse();
});
