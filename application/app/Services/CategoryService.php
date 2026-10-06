<?php

namespace App\Services;

use App\Data\Categories\CategoryData;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function getList(): Collection
    {
        return Category::all();
    }

    public function create(CategoryData $data): Category
    {
        return Category::create((array) $data);
    }

    public function update(Category $category, CategoryData $data): Category
    {
        $category->fill((array) $data);
        $category->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
