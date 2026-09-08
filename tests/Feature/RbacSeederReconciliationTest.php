<?php

namespace Tests\Feature;

use App\Models\ImprestFund;
use App\Models\ImprestTransaction;
use App\Models\Orphan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ImprestPermissionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('clean database can run canonical RolesAndPermissionsSeeder and DatabaseSeeder', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::count())->toBeGreaterThan(30);
    expect(Role::where('name', 'super_admin')->exists())->toBeTrue();
    expect(Role::where('name', 'admin')->exists())->toBeTrue();
    expect(Role::where('name', 'coordinator')->exists())->toBeTrue();
    expect(Role::where('name', 'auditor')->exists())->toBeTrue();

    // Verify DatabaseSeeder runs cleanly as well
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(0);
    $this->assertDatabaseCount('bank_accounts', 0);
});

test('canonical RBAC seeder is idempotent when executed repeatedly', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $initialPermCount = Permission::count();
    $initialRoleCount = Role::count();

    // Run again
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::count())->toBe($initialPermCount);
    expect(Role::count())->toBe($initialRoleCount);
});

test('running ImprestPermissionSeeder after RolesAndPermissionsSeeder does not erase unrelated permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $adminRole = Role::findByName('admin', 'web');
    $auditorRole = Role::findByName('auditor', 'web');

    expect($adminRole->hasPermissionTo('create_deceased'))->toBeTrue();
    expect($auditorRole->hasPermissionTo('view_reports'))->toBeTrue();

    // Execute ImprestPermissionSeeder
    $this->seed(ImprestPermissionSeeder::class);

    $adminRole->refresh();
    $auditorRole->refresh();

    expect($adminRole->hasPermissionTo('create_deceased'))->toBeTrue();
    expect($adminRole->hasPermissionTo('imprest.transactions.view'))->toBeTrue();

    expect($auditorRole->hasPermissionTo('view_reports'))->toBeTrue();
    expect($auditorRole->hasPermissionTo('imprest.transactions.view'))->toBeTrue();
});

test('auditor retains intended read permissions and no write/approval privileges', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $auditorUser = User::factory()->create();
    $auditorUser->assignRole('auditor');

    expect($auditorUser->hasPermissionTo('imprest.transactions.view'))->toBeTrue();
    expect($auditorUser->hasPermissionTo('imprest.funds.view'))->toBeTrue();
    expect($auditorUser->hasPermissionTo('view_reports'))->toBeTrue();
    expect($auditorUser->hasPermissionTo('export_reports'))->toBeTrue();
    expect($auditorUser->hasPermissionTo('orphan_education.analytics.view'))->toBeTrue();
    expect($auditorUser->hasPermissionTo('finance.consolidated_report.view'))->toBeTrue();

    // Negative assertions
    expect($auditorUser->hasPermissionTo('imprest.transactions.create'))->toBeFalse();
    expect($auditorUser->hasPermissionTo('create_deceased'))->toBeFalse();
    expect($auditorUser->hasPermissionTo('approve_widow_loans'))->toBeFalse();
});

test('current Imprest policies resolve correctly against seeded dotted permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $custodianUser = User::factory()->create();
    $custodianUser->assignRole('custodian');

    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $fund = ImprestFund::factory()->make([
        'custodian_id' => $custodianUser->id,
    ]);

    $transaction = ImprestTransaction::factory()->make([
        'custodian_id' => $custodianUser->id,
        'status' => 'pending',
    ]);

    expect(Gate::forUser($adminUser)->allows('viewAny', ImprestFund::class))->toBeTrue();
    expect(Gate::forUser($adminUser)->allows('viewAny', ImprestTransaction::class))->toBeTrue();

    expect(Gate::forUser($custodianUser)->allows('viewAny', ImprestFund::class))->toBeTrue();
    expect(Gate::forUser($custodianUser)->allows('create', ImprestTransaction::class))->toBeTrue();
});

test('Widow Loan approval permissions exist and intended roles receive them', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $directorRole = Role::findByName('director', 'web');
    $financeManagerRole = Role::findByName('finance_manager', 'web');
    $loanOfficerRole = Role::findByName('loan_officer', 'web');
    $adminRole = Role::findByName('admin', 'web');

    expect($directorRole->hasPermissionTo('view_approval_flows'))->toBeTrue();
    expect($directorRole->hasPermissionTo('approve_widow_loans'))->toBeTrue();
    expect($directorRole->hasPermissionTo('reject_widow_loans'))->toBeTrue();

    expect($financeManagerRole->hasPermissionTo('view_approval_flows'))->toBeTrue();
    expect($financeManagerRole->hasPermissionTo('approve_widow_loans'))->toBeTrue();

    expect($loanOfficerRole->hasPermissionTo('submit_widow_loans'))->toBeTrue();

    expect($adminRole->hasPermissionTo('approve_widow_loans'))->toBeTrue();
});

test('ID card permissions exist and intended roles receive them', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $adminRole = Role::findByName('admin', 'web');
    $coordinatorRole = Role::findByName('coordinator', 'web');

    expect(Permission::where('name', 'view_id_cards')->exists())->toBeTrue();
    expect(Permission::where('name', 'id_cards.create')->exists())->toBeTrue();
    expect(Permission::where('name', 'id_cards.bulk_print')->exists())->toBeTrue();

    expect($adminRole->hasPermissionTo('view_id_cards'))->toBeTrue();
    expect($adminRole->hasPermissionTo('id_cards.create'))->toBeTrue();

    expect($coordinatorRole->hasPermissionTo('view_id_cards'))->toBeTrue();
    expect($coordinatorRole->hasPermissionTo('id_cards.create'))->toBeFalse();
});

test('specialized domain permissions exist and are assigned to appropriate roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Check presence in permissions table
    expect(Permission::where('name', 'orphan_education.analytics.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'orphan_education.analytics.export')->exists())->toBeTrue();
    expect(Permission::where('name', 'orphan_education.override_academic_progression')->exists())->toBeTrue();

    expect(Permission::where('name', 'biometrics.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'biometrics.enroll')->exists())->toBeTrue();

    expect(Permission::where('name', 'finance.consolidated_report.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'out_of_pocket_expenditure.view')->exists())->toBeTrue();

    $adminRole = Role::findByName('admin', 'web');
    expect($adminRole->hasPermissionTo('orphan_education.analytics.view'))->toBeTrue();
    expect($adminRole->hasPermissionTo('biometrics.enroll'))->toBeTrue();
    expect($adminRole->hasPermissionTo('finance.consolidated_report.view'))->toBeTrue();
});

test('coordinator retains least privilege and does not receive privileged finance or approval permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $coordinatorRole = Role::findByName('coordinator', 'web');

    expect($coordinatorRole->hasPermissionTo('approve_loans'))->toBeFalse();
    expect($coordinatorRole->hasPermissionTo('approve_widow_loans'))->toBeFalse();
    expect($coordinatorRole->hasPermissionTo('imprest.manage_all'))->toBeFalse();
    expect($coordinatorRole->hasPermissionTo('imprest.funds.reconcile'))->toBeFalse();
    expect($coordinatorRole->hasPermissionTo('delete_deceased'))->toBeFalse();
});

test('super admin behavior remains consistent with Gate::before and protected model policies', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole('super_admin');

    // For normal model, Gate::before grants access
    expect(Gate::forUser($superAdmin)->allows('view_any_custom_ability'))->toBeTrue();

    // For protected User/Role/Permission/Orphan models, policy is evaluated
    expect(Gate::forUser($superAdmin)->denies('delete', $superAdmin))->toBeTrue();
});

function rbacRoleMatrix(): array
{
    return Role::with('permissions')->orderBy('name')->get()->mapWithKeys(fn (Role $role) => [
        $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
    ])->all();
}

test('pristine deployment bootstrap contains only system reference and RBAC rows', function () {
    expect(User::count())->toBe(0);
    $this->seed(DatabaseSeeder::class);

    // An allowlist across EVERY migrated table catches future operational seeders,
    // not only today's users, beneficiaries, bank accounts and financial tables.
    $systemTables = ['migrations', 'permissions', 'roles', 'role_has_permissions',
        'illnesses', 'id_card_templates', 'intervention_types', 'activities'];
    foreach (\Illuminate\Support\Facades\Schema::getTableListing(schemaQualified: false) as $table) {
        if (! in_array($table, $systemTables, true)) {
            $this->assertDatabaseCount($table, 0);
        }
    }
    foreach (['users', 'bank_accounts', 'deceased', 'widows', 'orphans', 'transactions',
        'widow_loans', 'repayments', 'widow_loan_repayments', 'welfare_packages', 'welfare_beneficiaries',
        'imprest_funds', 'imprest_transactions', 'journal_entries', 'journal_lines', 'ledgers'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect(\Illuminate\Support\Facades\DB::table('bank_accounts')->sum('opening_balance'))->toEqual(0);
    foreach (\Illuminate\Support\Facades\DB::table('activities')->get() as $log) {
        expect($log->subject_type)->toBeIn([Role::class, Permission::class]);
    }
    $counts = collect(\Illuminate\Support\Facades\Schema::getTableListing(schemaQualified: false))
        ->mapWithKeys(fn ($table) => [$table => \Illuminate\Support\Facades\DB::table($table)->count()])->all();
    $matrix = rbacRoleMatrix();
    $this->seed(DatabaseSeeder::class);
    foreach ($counts as $table => $count) {
        $this->assertDatabaseCount($table, $count);
    }
    expect(rbacRoleMatrix())->toBe($matrix);
});

test('mechanical source inventory has no active named permission missing from canonical bootstrap', function () {
    $inventory = \Tests\Support\AuthorizationPermissionInventory::scan();
    expect($inventory['unresolved'])->toBe([]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $canonical = Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
    expect($canonical)->toBe(\Tests\Support\AuthorizationPermissionInventory::canonicalPermissions());
    expect(array_values(array_diff(array_keys($inventory['permissions']), $canonical)))->toBe([]);
    // Policy methods are dispatch targets, not new Spatie permissions.
    expect($inventory['policy_calls'])->not->toBeEmpty();
    expect($canonical)->not->toContain('viewAny', 'update', 'resetMfa', 'manageStatus');

    foreach (['biometrics.revoke', 'biometrics.identify', 'biometrics.audit.view'] as $name) {
        if (isset($inventory['permissions'][$name])) {
            expect($canonical)->toContain($name);
        }
    }
    $imprest = array_filter(array_keys($inventory['permissions']), fn ($name) => str_starts_with($name, 'imprest.'));
    expect($imprest)->not->toBeEmpty();
    foreach ($imprest as $name) {
        expect($canonical)->toContain($name);
    }
});

test('specialized compatibility entry points are additive and preserve users and custom grants', function (string $seeder) {
    // First invocation on pristine storage has the same canonical definitions.
    $this->seed($seeder);
    $initialMatrix = rbacRoleMatrix();
    expect(User::count())->toBe(0);
    $this->assertDatabaseCount('bank_accounts', 0);
    $this->assertDatabaseCount('deceased', 0);
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(rbacRoleMatrix())->toBe($initialMatrix);

    $custom = Permission::create(['name' => 'organization.audit.read', 'guard_name' => 'web']);
    $external = Role::create(['name' => 'organization_reader', 'guard_name' => 'web']);
    $external->givePermissionTo($custom);
    Role::findByName('auditor')->givePermissionTo($custom);
    $user = User::factory()->create();
    $user->assignRole($external);
    $user->givePermissionTo($custom);
    $matrix = rbacRoleMatrix();
    $permissionIds = Permission::orderBy('uuid')->pluck('uuid')->all();
    $userAttributes = $user->fresh()->getAttributes();

    $this->seed($seeder);
    $this->seed($seeder);
    expect(rbacRoleMatrix())->toBe($matrix);
    expect(Permission::orderBy('uuid')->pluck('uuid')->all())->toBe($permissionIds);
    expect($user->fresh()->getAttributes())->toBe($userAttributes);
    expect($user->fresh()->roles->pluck('name')->all())->toBe(['organization_reader']);
    expect($user->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['organization.audit.read']);
    expect(User::count())->toBe(1);
    // Even super_admin receives only canonical grants, not arbitrary new records.
    expect(Role::findByName('super_admin')->hasPermissionTo($custom))->toBeFalse();
    $this->assertDatabaseCount('bank_accounts', 0);
    $this->assertDatabaseCount('transactions', 0);
})->with([
    \Database\Seeders\ImprestPermissionSeeder::class,
    \Database\Seeders\ApprovalPermissionsSeeder::class,
    \Database\Seeders\EducationVerifierRoleSeeder::class,
    \Database\Seeders\PermissionsTableSeeder::class,
    \Database\Seeders\RolesTableSeeder::class,
]);

test('auditor cannot reconcile or mutate funds and coordinator has only explicit field grants', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $fund = ImprestFund::factory()->make(['custodian_id' => User::factory()->create()->id]);
    foreach (['create', 'update', 'manageStatus', 'reconcile', 'replenish', 'approve'] as $ability) {
        expect(Gate::forUser($auditor)->denies($ability, $fund))->toBeTrue();
    }
    foreach ($auditor->getAllPermissions() as $permission) {
        expect(preg_match('/(^view_|^export_|^imprest_view_|\.(view|export)$)/', $permission->name))->toBe(1);
    }
    $expected = ['view_deceased', 'create_deceased', 'edit_deceased', 'view_orphans', 'create_orphans', 'edit_orphans',
        'view_widows', 'create_widows', 'edit_widows', 'view_zones', 'view_projects', 'create_projects',
        'create_education_interventions', 'create_healthcare_interventions', 'create_welfare_interventions',
        'create_loans', 'view_loans', 'view_id_cards', 'biometrics.view', 'biometrics.enroll', 'view_reports'];
    sort($expected);
    expect(rbacRoleMatrix()['coordinator'])->toBe($expected);
});

test('compatibility role spellings retain equivalent grants without duplicating assignments', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $matrix = rbacRoleMatrix();
    expect($matrix['custodian'])->toBe($matrix['finance-custodian']);
    expect($matrix['education-verifier'])->toBe($matrix['education_verifier']);
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(rbacRoleMatrix())->toBe($matrix);
});

test('super admin falls through to all protected model policies', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    expect(Gate::forUser($superAdmin)->denies('delete', $superAdmin))->toBeTrue();
    expect(Gate::forUser($superAdmin)->denies('delete', Role::findByName('super_admin')))->toBeTrue();
    $permission = Permission::first();
    expect(Gate::forUser($superAdmin)->allows('delete', $permission))
        ->toBe((new \App\Policies\PermissionPolicy)->delete($superAdmin, $permission));
    $permissionPolicy = \Mockery::mock(\App\Policies\PermissionPolicy::class)->makePartial();
    $permissionPolicy->shouldReceive('delete')->once()->andReturnFalse();
    app()->instance(\App\Policies\PermissionPolicy::class, $permissionPolicy);
    expect(Gate::forUser($superAdmin)->denies('delete', $permission))->toBeTrue();
    $orphan = new Orphan(['status' => \App\Enums\OrphanStatus::ARCHIVED, 'is_eligible' => false]);
    expect(Gate::forUser($superAdmin)->denies('delete', $orphan))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('view_any_custom_ability'))->toBeTrue();
});

test('admin retains operational and scoped user access without security definitions or global overrides', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $ordinary = User::factory()->create();
    $ordinary->assignRole('coordinator');
    $peer = User::factory()->create();
    $peer->assignRole('admin');
    $super = User::factory()->create();
    $super->assignRole('super_admin');

    foreach (['create_deceased', 'edit_widows', 'create_orphans', 'manage_projects',
        'verify_education_interventions', 'approve_healthcare_interventions', 'approve_welfare_interventions',
        'approve_widow_loans', 'disburse_widow_loans', 'collect_widow_loans', 'view_finances',
        'view_sponsorships', 'id_cards.create', 'export_reports', 'imprest.funds.reconcile',
        'imprest.funds.replenish', 'imprest.transactions.approve', 'assign_roles'] as $name) {
        expect($admin->hasPermissionTo($name))->toBeTrue();
    }
    foreach (['view_settings', 'edit_settings', 'create_roles', 'edit_roles', 'delete_roles', 'role_edit',
        'create_permissions', 'edit_permissions', 'delete_permissions', 'imprest.manage_all',
        'imprest.bypass_custodian_check', 'biometrics.override', 'orphan_education.override_academic_progression'] as $name) {
        expect($admin->hasPermissionTo($name))->toBeFalse();
        expect($admin->can($name))->toBeFalse();
    }
    expect(Gate::forUser($admin)->allows('create', User::class))->toBeTrue();
    foreach (['update', 'delete', 'resetPassword', 'resetMfa'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $ordinary))->toBeTrue();
        expect(Gate::forUser($admin)->denies($ability, $peer))->toBeTrue();
        expect(Gate::forUser($admin)->denies($ability, $super))->toBeTrue();
    }
    $this->actingAs($admin);
    foreach (['admin', 'coordinator', 'super_admin'] as $name) {
        $role = Role::findByName($name);
        expect(Gate::forUser($admin)->denies('update', $role))->toBeTrue();
        expect(\App\Filament\Resources\Roles\RoleResource::canEdit($role))->toBeFalse();
    }
    $permission = Permission::first();
    foreach (['create', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($admin)->denies($ability, Role::findByName('coordinator')))->toBeTrue();
        expect(Gate::forUser($admin)->denies($ability, $permission))->toBeTrue();
    }
    $fund = ImprestFund::factory()->make(['custodian_id' => $ordinary->id]);
    foreach (['view', 'create', 'update', 'reconcile', 'replenish', 'approve'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $fund))->toBeTrue();
    }
    $transaction = ImprestTransaction::factory()->make(['custodian_id' => $ordinary->id, 'status' => 'pending']);
    expect(Gate::forUser($admin)->allows('approve', $transaction))->toBeTrue();
    $transaction->custodian_id = $admin->id;
    expect(Gate::forUser($admin)->denies('approve', $transaction))->toBeTrue();
});

test('admin cannot use custodian bypass in middleware or transaction service', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    $fund = ImprestFund::factory()->create();
    $request = \Illuminate\Http\Request::create('/imprest', 'POST', ['fund_id' => $fund->id]);
    try {
        (new \App\Http\Middleware\Imprest\EnsureFundCustodian)->handle($request, fn () => response('Allowed'));
        $this->fail('Admin bypassed the fund custodian check.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    $fundRepo = \Mockery::mock(\App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface::class);
    $fundRepo->shouldReceive('findById')->once()->with($fund->id)->andReturn($fund);
    $transactionRepo = \Mockery::mock(\App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface::class);
    $transactionRepo->shouldNotReceive('create');
    $service = new \App\Services\Imprest\ImprestTransactionService($transactionRepo, $fundRepo);
    $dto = new \App\Data\Imprest\CreateTransactionDto(
        fundId: $fund->id, date: now(), deceasedId: null, name: 'Payee', expenseType: 'service',
        itemId: null, serviceDescription: 'Test', itemService: null, quantity: 1, unitPrice: 1,
        category: \App\Enums\TransactionCategory::cases()[0], paymentMethod: \App\Enums\PaymentMethod::cases()[0],
    );
    expect(fn () => $service->create($dto, $admin->id))->toThrow(\RuntimeException::class, 'You are not the custodian of this fund.');
});

test('super admin explicit custom grants follow canonical versus additive bootstrap semantics', function () {
    $custom = Permission::create(['name' => 'organization.private.review', 'guard_name' => 'web']);
    Permission::create(['name' => 'organization.api.review', 'guard_name' => 'api']);
    $this->seed(RolesAndPermissionsSeeder::class);
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    expect($super->hasPermissionTo($custom))->toBeFalse();
    expect($super->can($custom->name))->toBeTrue();
    $role = Role::findByName('super_admin');
    $role->givePermissionTo($custom);
    foreach ([\Database\Seeders\ImprestPermissionSeeder::class, \Database\Seeders\ApprovalPermissionsSeeder::class,
        \Database\Seeders\EducationVerifierRoleSeeder::class, \Database\Seeders\PermissionsTableSeeder::class,
        \Database\Seeders\RolesTableSeeder::class] as $seeder) {
        $this->seed($seeder);
        expect($role->fresh()->hasPermissionTo($custom))->toBeTrue();
    }
    $this->seed(RolesAndPermissionsSeeder::class);
    expect($role->fresh()->hasPermissionTo($custom))->toBeFalse();
    expect(Permission::where('name', $custom->name)->exists())->toBeTrue();
    expect($super->fresh()->can($custom->name))->toBeTrue();
    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(\Tests\Support\AuthorizationPermissionInventory::canonicalPermissions());
});

test('other operational role grants are unchanged by the admin boundary correction', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $expected = json_decode(file_get_contents(base_path('tests/Fixtures/rbac-operational-role-grants.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($expected as $role => $permissions) {
        expect(rbacRoleMatrix()[$role])->toBe($permissions);
    }
});
