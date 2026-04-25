<?php

namespace App\Data\Category;

use App\Data\Item\ItemData;
use App\Models\Category;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Optional;

class CategoryData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $description,
        public ?int $user_id,
        /** @var ItemData[]|Optional */
        public array|Optional $items,
    ) {}

    public static function fromModel(Category $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            description: $category->description,
            user_id: $category->user_id,
            items: Lazy::create(fn() => ItemData::collect($category->items)),
        );
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
