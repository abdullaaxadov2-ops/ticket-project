<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\ProfileService;

class NameChangeController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, ProfileService $service)
    {
        return [
            'success' => true,
            'data' => $service->changeName($request->user(), $request->validated('name')),
        ];
    }
}
