<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user('sanctum');
        $query = Event::query();

        if (!$user || $user->isParticipant()) {
            $query->where('status', EventStatus::Published);
        } elseif ($user->isOrganiser()) {
            $query->where(function ($q) use ($user) {
                $q->where('status', EventStatus::Published)
                    ->orWhere('organizer_id', $user->id);
            });
        }

        return $query->paginate(15);
    }

    public function show(Event $event)
    {
        return $event;
    }

    public function store(EventRequest $request)
    {
        $this->authorize('create', Event::class);

        $event = new Event($request->validated());
        $event->organizer_id = $request->user()->id;
        $event->status = EventStatus::Draft;
        $event->save();

        return response()->json($event, 201);
    }

    public function update(EventRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return $event;
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->noContent();
    }
}
