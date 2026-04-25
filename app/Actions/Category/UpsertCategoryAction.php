<?php

namespace App\Actions\Category;

use App\Models\Category;
use App\Models\Item;
use App\Data\Category\CategoryData;
use App\Data\Item\ItemData;

class UpsertCategoryAction
{
    public function execute(CategoryData $data, ?Category $category = null): Category
    {
        return Category::updateOrCreate(
            ['id' => $category?->id],
            [
                'name' => $data->name,
                'description' => $data->description,
                'user_id' => $data->user_id ?? auth()->id(),
            ]
        );
    }
}
