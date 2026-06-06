<?php

use App\Enums\Gender;
use App\Models\Orphan;
use App\Services\OrphanEligibilityService;

it('filters out male orphans once they are 18', function () {
    $service = new OrphanEligibilityService;

    $orphan = new Orphan([
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(18)->toDateString(),
        'is_eligible' => true,
        'status' => 'active',
    ]);

    expect($service->isEligible($orphan))->toBeFalse();
});

it('keeps male orphans eligible before they are 18', function () {
    $service = new OrphanEligibilityService;

    $orphan = new Orphan([
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(18)->addDay()->toDateString(),
        'is_eligible' => true,
        'status' => 'active',
    ]);

    expect($service->isEligible($orphan))->toBeTrue();
});

it('filters out married female orphans', function () {
    $service = new OrphanEligibilityService;

    $orphan = new Orphan([
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(12)->toDateString(),
        'is_married' => true,
        'is_eligible' => true,
        'status' => 'active',
    ]);

    expect($service->isEligible($orphan))->toBeFalse();
});
