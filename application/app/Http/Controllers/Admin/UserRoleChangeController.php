<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Models\User;
use App\Services\UserAdminService;

class UserRoleChangeController extends Controller
{
    public function __invoke(ChangeUserRoleRequest $request, User $user, UserAdminService $service)
    {
        return [
            'success' => true,
            'data' => $service->changeRole($user, UserRole::from($request->validated('role'))),
        ];
    }
}
