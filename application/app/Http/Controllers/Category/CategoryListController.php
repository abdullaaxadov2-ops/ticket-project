<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;

class CategoryListController extends Controller
{
    public function __invoke(CategoryService $service)
    {
        return [
            'data' => $service->getList(),
        ];
    }
}
