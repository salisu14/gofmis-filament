<?php

use App\Enums\StockMovementType;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockItem(string $name = 'Rice 10kg'): Item
{
    $user = \App\Models\User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $category = \App\Models\Category::create(['name' => 'Food', 'user_id' => $user->id]);

    return Item::create([
        'name' => $name,
        'category_id' => $category->id,
        'user_id' => $user->id,
        'reorder_level' => 20,
        'is_active' => true,
    ]);
}

function move(Item $item, StockMovementType $type, int $quantity): void
{
    $item->stockMovements()->create([
        'movement_type' => $type,
        'quantity' => $quantity,
        'occurred_at' => now(),
        'created_by' => \App\Models\User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE])->id,
    ]);
}

test('current stock derives from the ledger: opening 100 minus outbound 30 = 70', function () {
    $item = stockItem();
    move($item, StockMovementType::OPENING_BALANCE, 100);
    move($item, StockMovementType::WELFARE_ISSUE, -30);

    expect($item->current_stock)->toBe(70);
});

test('multiple inbound and outbound movements aggregate correctly', function () {
    $item = stockItem();
    move($item, StockMovementType::OPENING_BALANCE, 50);
    move($item, StockMovementType::PURCHASE_RECEIPT, 40);
    move($item, StockMovementType::DONATION_RECEIPT, 10);
    move($item, StockMovementType::WELFARE_ISSUE, -25);
    move($item, StockMovementType::INTERVENTION_ISSUE, -15);

    expect($item->current_stock)->toBe(60); // 50 + 40 + 10 - 25 - 15
});

test('zero stock when no movements exist', function () {
    $item = stockItem();
    expect($item->current_stock)->toBe(0);
});

test('current stock never goes negative (clamped to zero)', function () {
    $item = stockItem();
    move($item, StockMovementType::OPENING_BALANCE, 10);
    move($item, StockMovementType::WELFARE_ISSUE, -50);

    expect($item->current_stock)->toBe(0);
});

test('low stock classification uses reorder level against derived stock', function () {
    $item = stockItem();
    move($item, StockMovementType::OPENING_BALANCE, 10); // below reorder_level 20

    expect($item->current_stock)->toBe(10)
        ->and($item->current_stock <= $item->reorder_level)->toBeTrue();
});
