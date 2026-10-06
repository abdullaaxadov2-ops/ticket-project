<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Services\ProfileService;

class PasswordChangeController extends Controller
{
    public function __invoke(ChangePasswordRequest $request, ProfileService $service)
    {
        $service->changePassword($request->user(), $request->validated('new_password'));

        return ['success' => true];
    }
}
