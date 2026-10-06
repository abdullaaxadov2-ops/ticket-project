<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventShowController extends Controller
{
    public function __invoke(Request $request, Event $event)
    {
        if (Gate::forUser($request->user('sanctum'))->denies('view', $event)) {
            abort(404);
        }

        return [
            'data' => $event,
        ];
    }
}
