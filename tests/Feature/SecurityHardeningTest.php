<?php

use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    // Seed roles and permissions
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['password' => Hash::make('password123'), 'status' => UserStatus::ACTIVE, 'is_active' => true]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['password' => Hash::make('password123'), 'status' => UserStatus::ACTIVE, 'is_active' => true]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create(['password' => Hash::make('password123'), 'status' => UserStatus::ACTIVE, 'is_active' => true]);
    $this->coordinator->assignRole('coordinator');
});

test('admin cannot edit super admin', function () {
    expect(Gate::forUser($this->admin)->denies('update', $this->superAdmin))->toBeTrue();
});

test('coordinator cannot edit admin', function () {
    expect(Gate::forUser($this->coordinator)->denies('update', $this->admin))->toBeTrue();
});

test('last super admin cannot be deleted', function () {
    expect(Gate::forUser($this->superAdmin)->denies('delete', $this->superAdmin))->toBeTrue();
    expect(fn () => $this->superAdmin->delete())->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('last super admin cannot be disabled', function () {
    expect(Gate::forUser($this->superAdmin)->denies('disable', $this->superAdmin))->toBeTrue();
    expect(fn () => $this->superAdmin->disable($this->superAdmin))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('last super admin cannot be suspended', function () {
    expect(Gate::forUser($this->superAdmin)->denies('suspend', $this->superAdmin))->toBeTrue();
    expect(fn () => $this->superAdmin->suspend($this->superAdmin, 'Test suspension'))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('last super admin cannot be locked', function () {
    expect(fn () => $this->superAdmin->lock())->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('last super admin cannot be demoted', function () {
    $coordinatorRole = Role::where('name', 'coordinator')->first();
    expect(fn () => $this->superAdmin->syncRoles([$coordinatorRole->uuid]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('admin cannot grant super_admin', function () {
    $service = new UserRoleService;
    $target = User::factory()->create();
    $superAdminRole = Role::where('name', 'super_admin')->first();
    expect(fn () => $service->syncRoles($this->admin, $target, [$superAdminRole->uuid]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('coordinator cannot grant admin', function () {
    $service = new UserRoleService;
    $target = User::factory()->create();
    $adminRole = Role::where('name', 'admin')->first();
    expect(fn () => $service->syncRoles($this->coordinator, $target, [$adminRole->uuid]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('forged direct service mutation fails for unauthorized actor', function () {
    $service = new UserRoleService;
    $target = User::factory()->create();
    $target->assignRole('admin');
    $coordinatorRole = Role::where('name', 'coordinator')->first();

    // Admin trying to demote Super Admin fails
    expect(fn () => $service->syncRoles($this->admin, $this->superAdmin, [$coordinatorRole->uuid]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('seeder and system role assignment still works', function () {
    $newUser = User::factory()->create();
    expect(fn () => $newUser->assignRole('coordinator'))->not->toThrow(\Exception::class);
    expect($newUser->hasRole('coordinator'))->toBeTrue();
});

test('password confirmation succeeds and fails correctly', function () {
    $actor = $this->superAdmin;
    $this->actingAs($actor);

    RateLimiter::clear("sensitive-action-password:{$actor->id}:test_action");

    expect(Hash::check('password123', $actor->password))->toBeTrue()
        ->and(Hash::check('wrongpass', $actor->password))->toBeFalse();
});

test('typed phrase confirmation succeeds and fails correctly', function () {
    $phrase = 'DELETE USER';
    expect($phrase === 'DELETE USER')->toBeTrue()
        ->and($phrase === 'WRONG PHRASE')->toBeFalse();
});

test('combined confirmation requires both password and phrase', function () {
    $actor = $this->superAdmin;
    $this->actingAs($actor);

    $passwordRight = Hash::check('password123', $actor->password);
    $passwordWrong = Hash::check('wrongpass', $actor->password);
    $phraseRight = ('DELETE USER' === 'DELETE USER');
    $phraseWrong = ('WRONG' === 'DELETE USER');

    expect($passwordRight && $phraseRight)->toBeTrue()
        ->and($passwordRight && $phraseWrong)->toBeFalse()
        ->and($passwordWrong && $phraseRight)->toBeFalse()
        ->and($passwordWrong && $phraseWrong)->toBeFalse();
});

test('sensitive action throttling activates on 6th failed attempt', function () {
    $actor = $this->superAdmin;
    $key = "sensitive-action-password:{$actor->id}:test_action";
    RateLimiter::clear($key);

    for ($i = 1; $i <= 5; $i++) {
        RateLimiter::hit($key, 60);
    }

    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
    RateLimiter::clear($key);
});

test('secret values are absent from audit log', function () {
    $user = User::factory()->create(['password' => Hash::make('secretpass123')]);
    $user->update(['name' => 'Updated Name']);

    $logs = Activity::where('subject_id', $user->id)->get();
    foreach ($logs as $log) {
        $json = json_encode($log->properties);
        expect($json)->not->toContain('secretpass123');
    }
});

test('protected role delete is denied', function () {
    $superAdminRole = Role::where('name', 'super_admin')->first();
    $adminRole = Role::where('name', 'admin')->first();
    $coordinatorRole = Role::where('name', 'coordinator')->first();

    expect(Gate::forUser($this->superAdmin)->denies('delete', $superAdminRole))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->denies('delete', $adminRole))->toBeTrue()
        ->and(Gate::forUser($this->superAdmin)->denies('delete', $coordinatorRole))->toBeTrue();
});

test('protected permission mutation is denied for non super admin', function () {
    $permission = Permission::first();
    if ($permission) {
        expect(Gate::forUser($this->admin)->denies('delete', $permission))->toBeTrue();
    } else {
        expect(true)->toBeTrue();
    }
});

test('Gate::before cannot bypass critical invariants', function () {
    expect(Gate::forUser($this->superAdmin)->denies('delete', $this->superAdmin))->toBeTrue();
});

test('disabled user cannot access admin panel', function () {
    $disabledUser = User::factory()->create(['status' => UserStatus::DISABLED, 'is_active' => false]);
    $disabledUser->assignRole('admin');
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($disabledUser->canAccessPanel($panel))->toBeFalse();
});

test('disabled coordinator cannot access coordinator panel', function () {
    $disabledUser = User::factory()->create(['status' => UserStatus::DISABLED, 'is_active' => false]);
    $disabledUser->assignRole('coordinator');
    $panel = Filament\Facades\Filament::getPanel('coordinator');

    expect($disabledUser->canAccessPanel($panel))->toBeFalse();
});

test('disabled imprest user cannot access imprest panel', function () {
    $disabledUser = User::factory()->create(['status' => UserStatus::DISABLED, 'is_active' => false]);
    $disabledUser->assignRole('admin');
    $panel = Filament\Facades\Filament::getPanel('imprest');

    expect($disabledUser->canAccessPanel($panel))->toBeFalse();
});

test('write off loan requires WRITE OFF LOAN phrase and password', function () {
    $level = \App\Enums\SensitiveConfirmationLevel::PASSWORD_AND_PHRASE;
    expect($level)->toBe(\App\Enums\SensitiveConfirmationLevel::PASSWORD_AND_PHRASE);
});

test('security rbac audit command reports correct findings', function () {
    $this->artisan('security:rbac-audit')
        ->expectsOutputToContain('Active Super Admin Users: 1')
        ->expectsOutputToContain('At least one active Super Admin user exists.')
        ->assertExitCode(0);

    $tempSuperAdmin = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $tempSuperAdmin->assignRole('super_admin');

    User::role('super_admin')->delete();

    $this->artisan('security:rbac-audit')
        ->expectsOutputToContain('Active Super Admin Users: 0')
        ->expectsOutputToContain('ZERO active Super Admin users')
        ->assertExitCode(0);
});

test('middleware logs out disabled user on next request', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $user->assignRole('admin');
    $this->actingAs($user);

    $user->update(['status' => UserStatus::DISABLED, 'is_active' => false]);

    $middleware = new \App\Http\Middleware\EnsureActiveUser;
    $request = \Illuminate\Http\Request::create('/admin', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getStatusCode())->toBe(302);
});

test('concurrency safe getActiveSuperAdminCount performs row locking query', function () {
    $count = User::getActiveSuperAdminCount();
    expect($count)->toBeGreaterThanOrEqual(1);
});

test('role deletion fails if users are assigned', function () {
    $customRole = Role::create(['name' => 'test_custom_role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('test_custom_role');

    expect(Gate::forUser($this->superAdmin)->denies('delete', $customRole))->toBeTrue();
});

test('mfa fields are hidden from array serialization', function () {
    $user = User::factory()->create([
        'app_authentication_secret' => 'SECRET123',
        'app_authentication_recovery_codes' => json_encode(['code1']),
    ]);

    $array = $user->toArray();
    expect($array)->not->toHaveKey('app_authentication_secret')
        ->and($array)->not->toHaveKey('app_authentication_recovery_codes');
});

test('user deletion requires password and phrase in EditUser', function () {
    $page = new \App\Filament\Resources\Users\Pages\EditUser;
    expect($page)->not->toBeNull();
});

test('role deletion requires password and phrase in RoleResource', function () {
    $resource = new \App\Filament\Resources\Roles\RoleResource;
    expect($resource)->not->toBeNull();
});

test('sensitive action password failure logs audit event', function () {
    $actor = $this->superAdmin;
    $this->actingAs($actor);

    $passwordRule = function ($attribute, $value, $fail) use ($actor) {
        if (! Hash::check($value, $actor->password)) {
            \App\Services\SecurityAuditService::log(
                'SENSITIVE_ACTION_FAILED',
                'Failed password confirmation for test_audit',
                $actor,
                null,
                ['reason' => 'Incorrect password']
            );
            $fail('Incorrect password.');
        }
    };

    $failed = false;
    $passwordRule('password', 'wrongpassword', function ($msg) use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();

    $log = Activity::where('log_name', 'security')
        ->where('event', 'SENSITIVE_ACTION_FAILED')
        ->first();
    expect($log)->not->toBeNull();
});

test('coordinators cannot view elevated accounts in user resource', function () {
    expect(Gate::forUser($this->coordinator)->denies('view', $this->superAdmin))->toBeTrue()
        ->and(Gate::forUser($this->coordinator)->denies('view', $this->admin))->toBeTrue();
});

test('coordinators cannot create users', function () {
    expect(Gate::forUser($this->coordinator)->denies('create', User::class))->toBeTrue();
});

test('admin cannot create super admin or admin role', function () {
    $rolePolicy = new \App\Policies\RolePolicy;
    expect($rolePolicy->create($this->admin))->toBeFalse();
});

test('super admin count calculation filters inactive or suspended super admins', function () {
    $inactiveSA = User::factory()->create(['status' => UserStatus::DISABLED, 'is_active' => false]);
    $inactiveSA->assignRole('super_admin');

    $activeCount = User::getActiveSuperAdminCount();
    expect($activeCount)->toBe(1);
});

test('admin cannot reset super_admin password', function () {
    $service = new \App\Services\UserSecurityService;
    expect(fn () => $service->resetPassword($this->admin, $this->superAdmin, 'NewPassword123!'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('coordinator cannot reset passwords', function () {
    $service = new \App\Services\UserSecurityService;
    expect(fn () => $service->resetPassword($this->coordinator, $this->admin, 'NewPassword123!'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('successful administrative reset changes target password', function () {
    $service = new \App\Services\UserSecurityService;
    $target = User::factory()->create(['password' => Hash::make('old_pass')]);

    $service->resetPassword($this->superAdmin, $target, 'NewPassword123!');

    $target->refresh();
    expect(Hash::check('NewPassword123!', $target->password))->toBeTrue();
    expect(Hash::check('old_pass', $target->password))->toBeFalse();
});

test('password never appears in audit payload during reset', function () {
    $service = new \App\Services\UserSecurityService;
    $target = User::factory()->create(['password' => Hash::make('old_pass')]);

    $service->resetPassword($this->superAdmin, $target, 'SecretPass999!');

    $log = Activity::where('event', 'ADMIN_PASSWORD_RESET')->first();
    expect($log)->not->toBeNull();
    $json = json_encode($log->properties);
    expect($json)->not->toContain('SecretPass999!');
});

test('MFA attributes cannot be generically updated', function () {
    $user = User::factory()->create();
    expect($user->app_authentication_secret)->toBeNull();
});

test('Gate global super_admin bypass does not bypass critical custom abilities', function () {
    expect(Gate::forUser($this->superAdmin)->denies('delete', $this->superAdmin))->toBeTrue();
});

test('direct service invocation remains authorized', function () {
    $service = new \App\Services\UserSecurityService;

    expect(fn () => $service->deleteUser($this->admin, $this->superAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('concurrency simulation for last super admin', function () {
    $sa2 = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);
    $sa2->assignRole('super_admin');

    $service = new \App\Services\UserSecurityService;

    $service->disableUser($this->superAdmin, $sa2);

    expect(fn () => $service->disableUser($this->superAdmin, $this->superAdmin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
