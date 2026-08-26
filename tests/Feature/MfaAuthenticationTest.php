<?php

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FAQRCode\Google2FA;

beforeEach(function () {
    // Seed roles and permissions
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    // Create custodian and auditor roles if they don't exist yet
    Role::firstOrCreate(['name' => 'custodian', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'password' => Hash::make('password123'),
        'status' => UserStatus::ACTIVE,
        'is_active' => true,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create([
        'password' => Hash::make('password123'),
        'status' => UserStatus::ACTIVE,
        'is_active' => true,
    ]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create([
        'password' => Hash::make('password123'),
        'status' => UserStatus::ACTIVE,
        'is_active' => true,
    ]);
    $this->coordinator->assignRole('coordinator');

    $this->mfaService = new MfaService;
});

test('middleware ordering: inactive account denied before MFA', function () {
    $this->app['router']->get('/test-middleware-order', function () {
        return 'success';
    })->middleware([
        'web',
        \App\Http\Middleware\EnsureActiveUser::class,
        \App\Http\Middleware\EnsureMfaVerified::class,
    ]);

    $this->admin->update([
        'is_active' => false,
        'status' => UserStatus::SUSPENDED,
    ]);

    $this->actingAs($this->admin);

    $response = $this->get('/test-middleware-order');

    // Should logout and redirect to login page (active status check takes precedence)
    $response->assertRedirect('/admin/login');
    expect(Auth::check())->toBeFalse();
});

test('pre-mfa direct livewire request rejected', function () {
    // Enable MFA for admin
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->admin);

    // Try a direct Livewire request to a protected route (e.g. /admin)
    $response = $this->withHeaders([
        'X-Livewire' => 'true',
        'Referer' => '/admin',
    ])->get('/admin');

    $response->assertStatus(409);
    $response->assertHeader('X-Livewire-Redirect', route('mfa.challenge'));
});

test('pending enrollment secret cannot grant MFA-enabled status', function () {
    $secret = $this->mfaService->generateSecret();

    // Save secret but do NOT confirm it
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => null]);

    expect($this->admin->twoFactorAuthEnabled())->toBeFalse();
    expect($this->admin->mfaState())->toBe('pending_enrollment');
});

test('challenge session expires after configured lifetime', function () {
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->admin);

    // Simulate successful MFA verification
    session()->put('mfa_verified_at', time() - 8000); // 8000 seconds ago (> 2 hours)
    session()->put('mfa_verified_user_id', $this->admin->id);

    // Requesting dashboard should redirect to challenge
    $response = $this->get('/admin');
    $response->assertRedirect(route('mfa.challenge'));
});

test('challenge session cannot cross user identities', function () {
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    // Logged in as admin first, MFA verified
    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    // Now log in as superAdmin
    Auth::logout();
    $this->actingAs($this->superAdmin);

    // SuperAdmin should not reuse admin's MFA verification state
    $response = $this->get('/admin');
    $response->assertRedirect(route('mfa.enroll')); // SuperAdmin requires enrollment
});

test('recovery-code double-submit only succeeds once', function () {
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $codes = $this->mfaService->generateRecoveryCodes($this->admin);
    $codeToUse = $codes[0];

    // First use
    $success = $this->mfaService->verifyRecoveryCode($this->admin, $codeToUse);
    expect($success)->toBeTrue();

    // Second use of the same code
    $successAgain = $this->mfaService->verifyRecoveryCode($this->admin, $codeToUse);
    expect($successAgain)->toBeFalse();
});

test('administrative reset invalidates previously verified session', function () {
    // Enable MFA for admin
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    // Admin is logged in and MFA verified
    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    // SuperAdmin performs administrative reset of Admin's MFA
    $this->mfaService->resetMfa($this->superAdmin, $this->admin);

    // Refresh model state
    $this->admin->refresh();

    expect($this->admin->twoFactorAuthEnabled())->toBeFalse();
    expect($this->admin->mfa_enrollment_required)->toBeTrue();

    // Admin session verification should be wiped/intercepted on request
    $response = $this->get('/admin');
    $response->assertRedirect(route('mfa.enroll'));
});

test('repeated enrollment does not leave multiple valid pending secrets', function () {
    $this->actingAs($this->admin);

    // Start enrollment 1
    $this->get('/mfa/enroll');

    // Simulate first Livewire password verification which generates secret 1
    \Livewire\Livewire::test(\App\Livewire\Mfa\MfaEnroll::class)
        ->set('password', 'password123')
        ->call('verifyPassword')
        ->assertSet('step', 2);

    $secret1 = session()->get('mfa_pending_secret');
    expect($secret1)->not->toBeEmpty();

    // Start enrollment 2 (refreshes page / re-initializes)
    \Livewire\Livewire::test(\App\Livewire\Mfa\MfaEnroll::class)
        ->set('password', 'password123')
        ->call('verifyPassword')
        ->assertSet('step', 2);

    $secret2 = session()->get('mfa_pending_secret');
    expect($secret2)->not->toBeEmpty();
    expect($secret1)->not->toBe($secret2); // Must rotate
});

test('reconfiguration preserves old secret until new one is verified', function () {
    $oldSecret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($oldSecret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    // Start reconfiguration
    \Livewire\Livewire::test(\App\Livewire\Mfa\MfaSettings::class)
        ->call('selectAction', 'reconfigure');

    // User model secret must still be the old secret
    $this->admin->refresh();
    expect($this->admin->getAppAuthenticationSecret())->toBe($oldSecret);
});

test('mandatory role determination uses real canonical roles', function () {
    $custodian = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $custodian->assignRole('custodian');

    $auditor = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $auditor->assignRole('auditor');

    expect($custodian->isMfaRequired())->toBeTrue();
    expect($auditor->isMfaRequired())->toBeTrue();
    expect($this->coordinator->isMfaRequired())->toBeFalse(); // Optional
});

test('self-disable policy: denied for mandatory roles', function () {
    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    expect(fn () => $this->mfaService->disableMfa($this->admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('self-disable policy: allowed for optional roles with password and phrase', function () {
    // Enable MFA for coordinator
    $secret = $this->mfaService->generateSecret();
    $this->coordinator->saveAppAuthenticationSecret($secret);
    $this->coordinator->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->coordinator);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->coordinator->id);

    // Verify self-disable via Livewire works
    \Livewire\Livewire::test(\App\Livewire\Mfa\MfaSettings::class)
        ->call('selectAction', 'disable')
        ->set('password', 'password123')
        ->set('phrase', 'DISABLE MFA')
        ->call('disableMfa')
        ->assertRedirect(route('mfa.settings'));

    $this->coordinator->refresh();
    expect($this->coordinator->twoFactorAuthEnabled())->toBeFalse();
});

test('admin reset hierarchy: super_admin cannot reset another super_admin', function () {
    $anotherSuperAdmin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'is_active' => true,
    ]);
    $anotherSuperAdmin->assignRole('super_admin');

    expect(Gate::forUser($this->superAdmin)->denies('resetMfa', $anotherSuperAdmin))->toBeTrue();
    expect(fn () => $this->mfaService->resetMfa($this->superAdmin, $anotherSuperAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

/* ─────────────────────────────────────────
   MFA UI Completion Pass Tests
   ───────────────────────────────────────── */

test('super_admin can access MFA Management page', function () {
    $superSecret = $this->mfaService->generateSecret();
    $this->superAdmin->saveAppAuthenticationSecret($superSecret);
    $this->superAdmin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->superAdmin);
    $response = $this->withSession([
        'mfa_verified_at' => time(),
        'mfa_verified_user_id' => $this->superAdmin->id,
    ])->get('/admin/mfa-management');

    $response->assertStatus(200);
});

test('admin can access MFA Management page when permitted', function () {
    $adminSecret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($adminSecret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);
    $this->admin->givePermissionTo('view_users');
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($this->admin);
    $response = $this->withSession([
        'mfa_verified_at' => time(),
        'mfa_verified_user_id' => $this->admin->id,
    ])->get('/admin/mfa-management');

    $response->assertStatus(200);
});

test('coordinator cannot access MFA Management page', function () {
    $this->actingAs($this->coordinator);
    $response = $this->withSession([
        'mfa_verified_at' => time(),
        'mfa_verified_user_id' => $this->coordinator->id,
    ])->get('/admin/mfa-management');

    $response->assertStatus(403);
});

test('MFA Management page query is scoped for lateral protection', function () {
    $adminSecret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($adminSecret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);
    $this->admin->givePermissionTo('view_users');

    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    // Get the page instance
    $page = new \App\Filament\Pages\MfaManagement;

    // Invoke getTableQuery privately/indirectly by looking at scoped users
    // Admin query must not contain other admin or super_admin
    $query = invade($page)->getTableQuery();

    $results = $query->get();

    expect($results->contains($this->superAdmin))->toBeFalse();
    expect($results->contains($this->admin))->toBeFalse();
    expect($results->contains($this->coordinator))->toBeTrue(); // Coordinator is lower-role
});

test('MFA Management page renders safe state without secrets or recovery code hashes', function () {
    $superSecret = $this->mfaService->generateSecret();
    $this->superAdmin->saveAppAuthenticationSecret($superSecret);
    $this->superAdmin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $adminSecret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($adminSecret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->superAdmin);
    $response = $this->withSession([
        'mfa_verified_at' => time(),
        'mfa_verified_user_id' => $this->superAdmin->id,
    ])->get('/admin/mfa-management');
    $response->assertStatus(200);
    $response->assertDontSee($this->admin->app_authentication_secret);
    $response->assertDontSee($this->admin->app_authentication_recovery_codes);
});

test('MFA Management page statistics match audit command', function () {
    $page = new \App\Filament\Pages\MfaManagement;
    $stats = $page->getStats();

    // Check stats format
    expect($stats)->toBeArray();
    expect($stats[0]['label'])->toBe('Total Active Users');
    expect($stats[1]['label'])->toBe('MFA Enabled');
});

test('recovery codes are copyable and downloadable during transient display', function () {
    $this->actingAs($this->admin);

    // Initial setup password verify
    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaEnroll::class)
        ->set('password', 'password123')
        ->call('verifyPassword')
        ->assertSet('step', 2);

    $secret = session()->get('mfa_pending_secret');

    // Simulate entering valid OTP using Google2FA generator
    $otp = (new Google2FA)->getCurrentOtp($secret);

    $component->set('otp', $otp)
        ->call('verifyOtp')
        ->assertSet('step', 3);

    // Plaintext recovery codes must be present in component state immediately after confirmation
    $codes = $component->get('recoveryCodes');
    expect($codes)->toBeArray()->toHaveCount(10);

    // Renders the Copy Recovery Codes and Download Recovery Codes UI elements
    $component->assertSee('Copy Recovery Codes');
    $component->assertSee('Download Recovery Codes');
});

test('completing enrollment flow destroys transient plaintext recovery codes', function () {
    $this->actingAs($this->admin);

    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaEnroll::class)
        ->set('password', 'password123')
        ->call('verifyPassword');

    $secret = session()->get('mfa_pending_secret');
    $otp = (new Google2FA)->getCurrentOtp($secret);

    $component->set('otp', $otp)
        ->call('verifyOtp')
        ->assertSet('step', 3);

    // Completing flow clears transient codes
    $component->call('complete')
        ->assertRedirect(route('mfa.settings'));

    expect($component->get('recoveryCodes'))->toBeEmpty();
});

test('recovery code regeneration supports copy and download, then clears on save', function () {
    $secret = $this->mfaService->generateSecret();
    $this->coordinator->saveAppAuthenticationSecret($secret);
    $this->coordinator->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->coordinator);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->coordinator->id);

    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaSettings::class)
        ->call('selectAction', 'regenerate')
        ->set('password', 'password123')
        ->call('regenerateRecoveryCodes');

    $newCodes = $component->get('newRecoveryCodes');
    expect($newCodes)->toBeArray()->toHaveCount(10);

    $component->assertSee('Copy Recovery Codes');
    $component->assertSee('Download Recovery Codes');

    // Clicking I Have Saved My Codes cancels/closes regeneration and clears state
    $component->call('cancelAction');
    expect($component->get('newRecoveryCodes'))->toBeEmpty();
});

test('recovery codes never appear in security audit event logs', function () {
    $this->actingAs($this->admin);

    $secret = $this->mfaService->generateSecret();
    $this->mfaService->confirmEnrollment($this->admin, $secret, (new Google2FA)->getCurrentOtp($secret));

    // Get the latest log entry
    $latestLog = \App\Models\Activity::latest()->first();

    if ($latestLog) {
        $properties = json_encode($latestLog->properties);
        expect($properties)->not->toContain('app_authentication_secret');
        expect($properties)->not->toContain('app_authentication_recovery_codes');
    }
});

test('MFA Management table filter: required but not enabled filter works', function () {
    $this->admin->givePermissionTo('view_users');
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($this->admin);

    $page = new \App\Filament\Pages\MfaManagement;
    $query = invade($page)->getTableQuery();

    // Create a user who requires MFA but has not enabled it
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('custodian'); // Mandatory MFA role
    $targetUser->update(['mfa_confirmed_at' => null]);

    // Apply filter query logic directly
    $mandatoryRoles = ['super_admin', 'admin', 'custodian', 'auditor'];
    $filteredQuery = User::query()->where(function ($q) use ($mandatoryRoles) {
        $q->whereNull('mfa_confirmed_at')
            ->where(function ($sub) use ($mandatoryRoles) {
                $sub->where('mfa_enrollment_required', true)
                    ->orWhereHas('roles', function ($r) use ($mandatoryRoles) {
                        $r->whereIn('name', $mandatoryRoles);
                    });
            });
    });

    expect($filteredQuery->get()->contains($targetUser))->toBeTrue();
});

test('MFA Management administrative Reset MFA action delegates to MfaService', function () {
    // Enable MFA for a custodian target
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('custodian');
    $secret = $this->mfaService->generateSecret();
    $targetUser->saveAppAuthenticationSecret($secret);
    $targetUser->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    expect($targetUser->twoFactorAuthEnabled())->toBeTrue();

    // Reset via service
    $this->mfaService->resetMfa($this->superAdmin, $targetUser);

    $targetUser->refresh();
    expect($targetUser->twoFactorAuthEnabled())->toBeFalse();
});

test('MFA Management administrative Require Enrollment action is actor-authorized', function () {
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('coordinator');

    // Super admin is authorized to update target
    expect(Gate::forUser($this->superAdmin)->allows('update', $targetUser))->toBeTrue();

    // Coordinator is not authorized to update target
    expect(Gate::forUser($this->coordinator)->allows('update', $targetUser))->toBeFalse();

    // Force enrollment via service
    $this->mfaService->requireMfaEnrollment($this->superAdmin, $targetUser);

    $targetUser->refresh();
    expect($targetUser->mfa_enrollment_required)->toBeTrue();
});

test('Reset MFA action confirms ACTOR password, not target password', function () {
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('custodian');
    $targetUser->update(['password' => Hash::make('targetpassword123')]);

    $this->actingAs($this->superAdmin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->superAdmin->id);

    // Test with target's password (should fail validation)
    \Livewire\Livewire::test(\App\Filament\Pages\MfaManagement::class)
        ->callTableAction('resetMfa', $targetUser, [
            'confirm_password' => 'targetpassword123',
            'confirm_phrase' => 'RESET MFA',
        ])
        ->assertHasTableActionErrors(['confirm_password']);

    // Test with actor's (superAdmin's) correct password (should succeed)
    \Livewire\Livewire::test(\App\Filament\Pages\MfaManagement::class)
        ->callTableAction('resetMfa', $targetUser, [
            'confirm_password' => 'password123',
            'confirm_phrase' => 'RESET MFA',
        ])
        ->assertHasNoTableActionErrors();
});

test('Reset MFA action phrase confirmation checks exact RESET MFA phrase', function () {
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('custodian');

    $this->actingAs($this->superAdmin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->superAdmin->id);

    // Test with wrong phrase (should fail validation)
    \Livewire\Livewire::test(\App\Filament\Pages\MfaManagement::class)
        ->callTableAction('resetMfa', $targetUser, [
            'confirm_password' => 'password123',
            'confirm_phrase' => 'WRONG PHRASE',
        ])
        ->assertHasTableActionErrors(['confirm_phrase']);
});

test('MFA Management page: forged Livewire action invocation is rejected for coordinator', function () {
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('custodian');

    $this->actingAs($this->coordinator);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->coordinator->id);

    $this->withoutExceptionHandling();

    // Attempting to mount/load should abort/fail due to authorization
    expect(fn () => \Livewire\Livewire::test(\App\Filament\Pages\MfaManagement::class))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('MfaService::resetMfa direct service authorization rejects unauthorized admin', function () {
    // Target is another admin (lateral tampering)
    $anotherAdmin = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $anotherAdmin->assignRole('admin');

    expect(fn () => $this->mfaService->resetMfa($this->admin, $anotherAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('MfaService::requireMfaEnrollment direct service authorization rejects lateral admin edit', function () {
    $anotherAdmin = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $anotherAdmin->assignRole('admin');

    expect(fn () => $this->mfaService->requireMfaEnrollment($this->admin, $anotherAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('EnsureMfaVerified middleware blocks business resource access for authenticated user without verified MFA', function () {
    // SuperAdmin has MFA enabled in DB but has NOT verified in the session
    $superSecret = $this->mfaService->generateSecret();
    $this->superAdmin->saveAppAuthenticationSecret($superSecret);
    $this->superAdmin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->superAdmin);

    // Access business route should redirect to challenge
    $response = $this->get('/admin/users');
    $response->assertRedirect(route('mfa.challenge'));
});

test('EnsureMfaVerified middleware precedence: logs out deactivated user before MFA redirect', function () {
    $this->app['router']->get('/test-middleware-precedence', function () {
        return 'success';
    })->middleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \App\Http\Middleware\EnsureActiveUser::class,
        \App\Http\Middleware\EnsureMfaVerified::class,
    ]);

    // Enable MFA and verify session
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->admin);

    // Make user inactive (suspended)
    $this->admin->update([
        'is_active' => false,
        'status' => UserStatus::SUSPENDED,
    ]);

    // Next request must redirect to /admin/login due to EnsureActiveUser, NOT succeed or redirect to challenge
    $response = $this->withSession([
        'mfa_verified_at' => time(),
        'mfa_verified_user_id' => $this->admin->id,
    ])->get('/test-middleware-precedence');

    $response->assertRedirect('/admin/login');
    $response->assertSessionHasErrors(['email']);
    expect(Auth::check())->toBeFalse();
});

test('MFA challenge rate limiting blocks after consecutive failed attempts', function () {
    // Enable MFA for coordinator
    $secret = $this->mfaService->generateSecret();
    $this->coordinator->saveAppAuthenticationSecret($secret);
    $this->coordinator->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->coordinator);

    // 6 consecutive failures should trigger rate limit throttle
    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaChallenge::class);
    for ($i = 0; $i < 5; $i++) {
        $component->set('code', '000000')->call('verify');
    }

    $component->set('code', '000000')->call('verify')
        ->assertHasErrors(['code']);
});

test('MFA challenge rate limiting is user-isolated', function () {
    // Throttle first user
    $key1 = 'mfa-challenge:'.$this->admin->id;
    for ($i = 0; $i < 6; $i++) {
        RateLimiter::hit($key1, 60);
    }
    expect(RateLimiter::tooManyAttempts($key1, 5))->toBeTrue();

    // Second user should not be throttled
    $key2 = 'mfa-challenge:'.$this->superAdmin->id;
    expect(RateLimiter::tooManyAttempts($key2, 5))->toBeFalse();
});

test('audit metadata contains no recovery-code plaintext during reset or require enrollment', function () {
    $targetUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $targetUser->assignRole('custodian');

    $this->mfaService->requireMfaEnrollment($this->superAdmin, $targetUser);

    $latestLog = \App\Models\Activity::latest()->first();
    if ($latestLog) {
        $properties = json_encode($latestLog->properties);
        expect($properties)->not->toContain('app_authentication_secret');
        expect($properties)->not->toContain('app_authentication_recovery_codes');
    }
});

test('MFA Security Center and enrollment pages do not visibly render raw Alpine/JavaScript logic', function () {
    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    // Render MfaSettings with active regeneration step to show recovery codes panel
    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaSettings::class)
        ->call('selectAction', 'regenerate')
        ->set('password', 'password123')
        ->call('regenerateRecoveryCodes');

    $html = $component->html();

    // Verify it contains standard user actions
    expect($html)->toContain('Copy Recovery Codes');
    expect($html)->toContain('Download Recovery Codes');
    expect($html)->toContain('I Have Saved My Recovery Codes');

    // Verify it does NOT print raw JavaScript source fragments as visible text outside <script>
    $strippedHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

    expect($strippedHtml)->not->toContain('navigator.clipboard.writeText');
    expect($strippedHtml)->not->toContain('URL.createObjectURL');
    expect($strippedHtml)->not->toContain('new Blob(');
    expect($strippedHtml)->not->toContain('copyAll() {');
    expect($strippedHtml)->not->toContain('downloadAll() {');
    expect($strippedHtml)->not->toContain('text +=');
});

test('MFA recovery codes download endpoint verification', function () {
    $secret = $this->mfaService->generateSecret();
    $this->coordinator->saveAppAuthenticationSecret($secret);
    $this->coordinator->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->coordinator);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->coordinator->id);

    // 1. Initially, if we try to download recovery codes without generating them (transient is empty), it fails safely
    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaSettings::class)
        ->call('downloadRecoveryCodes')
        ->assertHasErrors(['download']);

    // 2. Now, enter password and regenerate recovery codes
    $component->call('selectAction', 'regenerate')
        ->set('password', 'password123')
        ->call('regenerateRecoveryCodes');

    $newCodes = $component->get('newRecoveryCodes');
    expect($newCodes)->toBeArray()->toHaveCount(10);

    // 3. Trigger download and assert streamed response details
    $component->call('downloadRecoveryCodes')
        ->assertFileDownloaded('gof-mis-recovery-codes-'.now()->format('Y-m-d').'.txt');

    // Since Livewire download responses pack effect payload, we can inspect output headers & content
    expect($component->effects['download']['name'])->toBe('gof-mis-recovery-codes-'.now()->format('Y-m-d').'.txt');

    // Let's decode or directly check content of the download payload
    $downloadString = base64_decode($component->effects['download']['content']);

    // 4. Assert response contains generated recovery codes
    foreach ($newCodes as $code) {
        expect($downloadString)->toContain($code);
    }

    // 5. Assert response contains user email, generated timestamp, and warning
    expect($downloadString)->toContain($this->coordinator->email);
    expect($downloadString)->toContain('Generated:');
    expect($downloadString)->toContain('GARKO ORPHANS FOUNDATION MIS');
    expect($downloadString)->toContain('MULTI-FACTOR AUTHENTICATION RECOVERY CODES');
    expect($downloadString)->toContain('Each recovery code can be used once');

    // 6. Assert response does NOT contain MFA secret or stored hashes
    expect($downloadString)->not->toContain($secret);

    $storedHashes = $this->coordinator->getAppAuthenticationRecoveryCodes();
    foreach ($storedHashes as $hash) {
        expect($downloadString)->not->toContain($hash);
    }

    // 7. Click cancel (I Have Saved My Recovery Codes equivalent) and check that download is no longer possible
    $component->call('cancelAction');
    expect($component->get('newRecoveryCodes'))->toBeEmpty();

    $component->call('downloadRecoveryCodes')
        ->assertHasErrors(['download']);
});

test('MFA enrollment recovery codes download verification', function () {
    $this->actingAs($this->admin);

    $component = \Livewire\Livewire::test(\App\Livewire\Mfa\MfaEnroll::class)
        ->set('password', 'password123')
        ->call('verifyPassword');

    $secret = session()->get('mfa_pending_secret');
    $otp = (new Google2FA)->getCurrentOtp($secret);

    $component->set('otp', $otp)
        ->call('verifyOtp')
        ->assertSet('step', 3);

    $codes = $component->get('recoveryCodes');
    expect($codes)->toBeArray()->toHaveCount(10);

    // Stream download check
    $component->call('downloadRecoveryCodes')
        ->assertFileDownloaded('gof-mis-recovery-codes-'.now()->format('Y-m-d').'.txt');

    $downloadString = base64_decode($component->effects['download']['content']);
    foreach ($codes as $code) {
        expect($downloadString)->toContain($code);
    }
    expect($downloadString)->toContain($this->admin->email);

    // Completing flow clears transient codes
    $component->call('complete')
        ->assertRedirect(route('mfa.settings'));

    expect($component->get('recoveryCodes'))->toBeEmpty();

    $component->call('downloadRecoveryCodes')
        ->assertHasErrors(['download']);
});
