<?php

namespace App\Data\Item;

use App\Data\Category\CategoryData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ItemData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public int $category_id,
        public ?string $sku,
        public float $price,
        public ?int $user_id,
        public CategoryData|Optional $category,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'unique:items,sku'],
        ];
    }
}
