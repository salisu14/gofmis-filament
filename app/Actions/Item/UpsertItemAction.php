<?php

namespace App\Actions\Item;

use App\Data\Item\ItemData;
use App\Models\Item;

class UpsertItemAction
{
    public function execute(ItemData $data, ?Item $item = null): Item
    {
        return Item::updateOrCreate(
            ['id' => $item?->id],
            [
                'name' => $data->name,
                'category_id' => $data->category_id,
                'price' => $data->price,
                'sku' => $data->sku,
                'user_id' => $data->user_id ?? auth()->id(),
            ]
        );
    }
}
