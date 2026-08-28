<?php

use App\Filament\Resources\WidowLoanRepayments\Pages\ListWidowLoanRepayments;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Test Zone Alpha']);
});

test('weekly repayment report action renders and form fields exist', function () {
    Livewire::actingAs($this->admin)
        ->test(ListWidowLoanRepayments::class)
        ->assertTableActionExists('weeklyReport');
});

test('submitting weekly repayment report with week only does not throw TypeError and redirects to report route', function () {
    $today = now()->format('Y-m-d');

    Livewire::actingAs($this->admin)
        ->test(ListWidowLoanRepayments::class)
        ->callTableAction('weeklyReport', data: [
            'week' => $today,
        ])
        ->assertHasNoTableActionErrors()
        ->assertRedirect(route('wrl.weekly.download', ['week' => $today]));
});

test('submitting weekly repayment report with week and zone does not throw and includes zone in redirect', function () {
    $today = now()->format('Y-m-d');

    Livewire::actingAs($this->admin)
        ->test(ListWidowLoanRepayments::class)
        ->callTableAction('weeklyReport', data: [
            'week' => $today,
            'zone' => $this->zone->id,
        ])
        ->assertHasNoTableActionErrors()
        ->assertRedirect(route('wrl.weekly.download', ['week' => $today, 'zone' => $this->zone->id]));
});

test('admin can access and download the report route directly', function () {
    $today = now()->format('Y-m-d');

    $response = $this->actingAs($this->admin)
        ->get(route('wrl.weekly.download', ['week' => $today]));

    $response->assertOk();
});

test('zone filtering is preserved when downloading report with zone parameter', function () {
    $today = now()->format('Y-m-d');

    $response = $this->actingAs($this->admin)
        ->get(route('wrl.weekly.download', ['week' => $today, 'zone' => $this->zone->id]));

    $response->assertOk();
});

test('unauthorized guest user cannot access weekly repayment report route', function () {
    $today = now()->format('Y-m-d');

    $response = $this->get(route('wrl.weekly.download', ['week' => $today]));

    $response->assertRedirect();
});
