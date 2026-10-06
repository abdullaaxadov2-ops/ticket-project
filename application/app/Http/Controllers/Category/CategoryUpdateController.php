<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;

class CategoryUpdateController extends Controller
{
    public function __invoke(CategoryRequest $request, Category $category, CategoryService $service)
    {
        return [
            'success' => true,
            'data' => $service->update($category, $request->toDTO()),
        ];
    }
}
