<?php

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

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

    $this->custodian = User::factory()->create([
        'password' => Hash::make('password123'),
        'status' => UserStatus::ACTIVE,
        'is_active' => true,
    ]);
    $this->custodian->assignRole('custodian');

    $this->mfaService = new MfaService;
});

test('1. unauthenticated GET /mfa/challenge redirects cleanly to login without 500 or RouteNotFoundException', function () {
    $response = $this->get('/mfa/challenge');

    $response->assertStatus(302);
    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('2. expired session on MFA challenge redirects safely without referencing undefined login route', function () {
    $response = $this->withSession([])->get('/mfa/challenge');

    $response->assertStatus(302);
    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('3. unauthenticated Admin protected route redirects to Admin login', function () {
    $response = $this->get('/admin/users');

    $response->assertStatus(302);
    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('4. unauthenticated Coordinator protected route redirects to Coordinator login', function () {
    $response = $this->get('/coordinator/orphans');

    $response->assertStatus(302);
    $response->assertRedirect(route('filament.coordinator.auth.login'));
});

test('5. unauthenticated Imprest protected route redirects to Imprest login', function () {
    $response = $this->get('/imprest/imprest-funds');

    $response->assertStatus(302);
    $response->assertRedirect(route('filament.imprest.auth.login'));
});

test('6. authenticated non-MFA-verified mandatory-role user still reaches MFA challenge', function () {
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);

    $this->actingAs($this->admin);

    $response = $this->get('/admin');

    $response->assertStatus(302);
    $response->assertRedirect(route('mfa.challenge'));

    $challengeResponse = $this->get('/mfa/challenge');
    $challengeResponse->assertStatus(200);
    $challengeResponse->assertSeeLivewire(\App\Livewire\Mfa\MfaChallenge::class);
});

test('7. MFA-verified user can access intended protected page', function () {
    $secret = $this->mfaService->generateSecret();
    $this->admin->saveAppAuthenticationSecret($secret);
    $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);
    $this->admin->givePermissionTo('view_users');
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($this->admin);
    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    $response = $this->get('/admin/users');
    $response->assertStatus(200);
});

test('8. logout and session invalidation removes authentication normally', function () {
    $this->actingAs($this->admin);

    $this->post('/admin/logout');

    expect(Auth::check())->toBeFalse();

    $response = $this->get('/admin');
    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('9. no redirect loop between login page and MFA challenge', function () {
    $this->get('/admin/login')->assertStatus(200);
    $this->get('/coordinator/login')->assertStatus(200);
    $this->get('/imprest/login')->assertStatus(200);

    $response = $this->get('/mfa/challenge');
    $response->assertStatus(302);
    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('10. Livewire protected request after session expiry fails or redirects gracefully without 500', function () {
    $response = $this->withHeaders([
        'X-Livewire' => 'true',
    ])->get('/admin');

    expect($response->status())->toBeIn([302, 401, 409]);
    expect($response->status())->not->toBe(500);

    $responsePost = $this->withHeaders([
        'X-Livewire' => 'true',
    ])->post('/livewire/update');

    expect($responsePost->status())->not->toBe(500);
});
