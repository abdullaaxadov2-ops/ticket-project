<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;

class CategoryDeletionController extends Controller
{
    public function __invoke(Category $category, CategoryService $service)
    {
        $service->delete($category);

        return ['success' => true];
    }
}
