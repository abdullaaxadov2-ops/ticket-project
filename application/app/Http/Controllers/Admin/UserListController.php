<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserAdminService;

class UserListController extends Controller
{
    public function __invoke(UserAdminService $service)
    {
        return $service->getList();
    }
}
