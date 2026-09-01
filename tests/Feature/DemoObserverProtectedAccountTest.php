<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\MfaService;
use App\Services\UserSecurityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create([
        'email' => 'superadmin@gofmis.org',
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create([
        'email' => 'admin@gofmis.org',
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->admin->assignRole('admin');

    $this->protectedDemo = User::factory()->create([
        'email' => 'demo@gofmis.org',
        'is_active' => true,
        'is_protected_system_account' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->protectedDemo->assignRole('demo_observer');

    $this->ordinaryUser = User::factory()->create([
        'email' => 'coordinator@gofmis.org',
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->ordinaryUser->assignRole('coordinator');
});

test('1. isProtectedSystemAccount returns true for protected demo account', function () {
    expect($this->protectedDemo->isProtectedSystemAccount())->toBeTrue();
    expect($this->ordinaryUser->isProtectedSystemAccount())->toBeFalse();
});

test('2. Gate denies delete action for protected demo account even for Super Admin', function () {
    actingAs($this->superAdmin);

    expect(Gate::allows('delete', $this->protectedDemo))->toBeFalse();
});

test('3. Direct Eloquent delete attempt on protected demo account throws RuntimeException', function () {
    expect(fn () => $this->protectedDemo->delete())
        ->toThrow(\RuntimeException::class, 'Protected system accounts cannot be deleted.');
});

test('4. UserSecurityService deleteUser attempt on protected demo account throws ValidationException', function () {
    $service = app(UserSecurityService::class);

    expect(fn () => $service->deleteUser($this->superAdmin, $this->protectedDemo))
        ->toThrow(ValidationException::class);
});

test('5. demo_observer role cannot be removed from protected demo account', function () {
    expect(fn () => $this->protectedDemo->removeRole('demo_observer'))
        ->toThrow(\RuntimeException::class, 'The demo_observer role cannot be removed');
});

test('6. protected demo account cannot be reassigned to normal user role', function () {
    expect(fn () => $this->protectedDemo->syncRoles(['coordinator']))
        ->toThrow(\RuntimeException::class, 'Protected system accounts must retain the demo_observer role');
});

test('7. protected demo account cannot be disabled or suspended', function () {
    $service = app(UserSecurityService::class);

    expect(fn () => $service->disableUser($this->superAdmin, $this->protectedDemo))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->suspendUser($this->superAdmin, $this->protectedDemo, 'Test reason'))
        ->toThrow(ValidationException::class);
});

test('8. ordinary users remain deletable by Super Admin according to existing rules', function () {
    actingAs($this->superAdmin);

    expect(Gate::allows('delete', $this->ordinaryUser))->toBeTrue();

    $service = app(UserSecurityService::class);
    $service->deleteUser($this->superAdmin, $this->ordinaryUser);

    expect(User::where('id', $this->ordinaryUser->id)->exists())->toBeFalse();
});

test('9. Super Admin account protections remain unchanged', function () {
    actingAs($this->superAdmin);

    // Super Admin cannot delete self
    expect(Gate::allows('delete', $this->superAdmin))->toBeFalse();

    $service = app(UserSecurityService::class);
    expect(fn () => $service->deleteUser($this->superAdmin, $this->superAdmin))
        ->toThrow(ValidationException::class);
});

test('10. Super Admin CAN reset protected demo account password through approved path', function () {
    actingAs($this->superAdmin);

    expect(Gate::allows('resetPassword', $this->protectedDemo))->toBeTrue();

    $service = app(UserSecurityService::class);
    $service->resetPassword($this->superAdmin, $this->protectedDemo, 'NewDemoPassword123!');

    $this->protectedDemo->refresh();
    expect(\Illuminate\Support\Facades\Hash::check('NewDemoPassword123!', $this->protectedDemo->password))->toBeTrue();
});

test('11. ProvisionDemoObserverCommand is idempotent and sets protected system flag', function () {
    $this->artisan('gof:provision-demo-observer', [
        '--email' => 'demo@gofmis.org',
        '--name' => 'Demo Observer Updated',
    ])->assertSuccessful();

    $user = User::where('email', 'demo@gofmis.org')->first();
    expect($user->isProtectedSystemAccount())->toBeTrue();
    expect($user->hasRole('demo_observer'))->toBeTrue();
    expect($user->name)->toBe('Demo Observer Updated');
});

test('12. demo_observer is NOT a mandatory MFA role while super_admin and admin remain mandatory', function () {
    expect($this->protectedDemo->isMfaMandatoryByRole())->toBeFalse();
    expect($this->superAdmin->isMfaMandatoryByRole())->toBeTrue();
    expect($this->admin->isMfaMandatoryByRole())->toBeTrue();
});

test('13. demo observer can log into Admin panel with normal credentials without mandatory MFA challenge', function () {
    actingAs($this->protectedDemo);

    $this->get('/admin')
        ->assertStatus(200)
        ->assertSee('Dashboard');
});

test('14. demo observer cannot modify MFA settings or reset another user MFA', function () {
    actingAs($this->protectedDemo);

    $mfaService = app(MfaService::class);

    expect(fn () => $mfaService->resetMfa($this->protectedDemo, $this->admin))
        ->toThrow(\App\Exceptions\DemoReadOnlyException::class);
});

test('15. existing MFA enrollment on demo observer account is preserved if present', function () {
    $this->protectedDemo->update([
        'app_authentication_secret' => 'PRESERVEDSECRET123',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);

    expect($this->protectedDemo->twoFactorAuthEnabled())->toBeTrue();
    expect($this->protectedDemo->isMfaMandatoryByRole())->toBeFalse();
});
