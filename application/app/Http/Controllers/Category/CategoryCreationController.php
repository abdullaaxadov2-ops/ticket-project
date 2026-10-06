<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CategoryRequest;
use App\Services\CategoryService;

class CategoryCreationController extends Controller
{
    public function __invoke(CategoryRequest $request, CategoryService $service)
    {
        $category = $service->create($request->toDTO());

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }
}
