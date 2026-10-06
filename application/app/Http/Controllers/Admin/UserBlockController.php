<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserBlockRequest;
use App\Models\User;
use App\Services\UserAdminService;

class UserBlockController extends Controller
{
    public function __invoke(ChangeUserBlockRequest $request, User $user, UserAdminService $service)
    {
        return [
            'success' => true,
            'data' => $service->changeBlockStatus($user, $request->boolean('is_blocked')),
        ];
    }
}
