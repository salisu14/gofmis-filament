<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin dashboard renders cleanly without emitting raw PHP source code', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create([
        'password' => Hash::make('password123'),
        'status' => UserStatus::ACTIVE,
        'is_active' => true,
    ]);
    $admin->assignRole('super_admin');

    $this->actingAs($admin);

    $routes = [
        '/admin',
        '/admin/intervention-requests',
        '/admin/education-verification',
    ];

    foreach ($routes as $route) {
        $response = $this->withSession([
            'mfa_verified_at' => time(),
            'mfa_verified_user_id' => $admin->id,
        ])->get($route);

        if ($response->status() === 302) {
            $response = $this->followRedirects($response);
        }

        $response->assertStatus(200);

        $content = $response->getContent();

        expect($content)->not->toContain('use App\Filament\Actions\VerifyEducationRequestAction;');
        expect($content)->not->toContain('use App\Filament\Actions\ApproveInterventionRequestAction;');
        expect($content)->not->toContain('use App\Filament\Actions\RejectInterventionRequestAction;');
        expect($content)->not->toContain('use App\Filament\Actions\StartInterventionRequestReviewAction;');
        expect($content)->not->toContain('namespace App\Filament');
        expect($content)->not->toContain('<?php');
        expect($content)->not->toContain('?>');
    }
});
