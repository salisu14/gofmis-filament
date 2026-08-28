<?php

use App\Filament\Pages\Reports\ProjectReport;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'uuid' => \Illuminate\Support\Str::uuid()]);
    Role::firstOrCreate(['name' => 'admin', 'uuid' => \Illuminate\Support\Str::uuid()]);
    Role::firstOrCreate(['name' => 'coordinator', 'uuid' => \Illuminate\Support\Str::uuid()]);
});

it('registers project report in admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    actingAs($user);

    // Get the admin panel
    $panel = filament()->getPanel('admin');

    // Check if the page is registered
    expect($panel->getPages())->toContain(ProjectReport::class);
});

it('allows super admin to render project report', function () {
    $user = User::factory()->create([
        'is_active' => true,
        'status' => \App\Enums\UserStatus::ACTIVE,
        'app_authentication_secret' => 'secret-enrolled',
        'mfa_confirmed_at' => now(),
    ]);
    $user->assignRole('super_admin');

    actingAs($user);
    session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $user->id]);

    get(ProjectReport::getUrl())
        ->assertSuccessful();
});

it('denies unauthorized user from rendering project report', function () {
    $user = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    // No roles assigned

    actingAs($user);
    session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $user->id]);

    get(ProjectReport::getUrl())
        ->assertForbidden();
});
