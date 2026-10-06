<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileShowController extends Controller
{
    public function __invoke(Request $request)
    {
        return [
            'data' => $request->user(),
        ];
    }
}
