<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Http\Requests\EventIndexRequest;
use App\Models\TicketType;

class EventController extends Controller
{
    public function index(EventIndexRequest $request)
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

        if ($request->filled('search')) {
            $query->whereLike('title', '%' . $request->search . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('venue_id')) {
            $query->where('venue_id', $request->venue_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('starts_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('starts_at', '<=', $request->date_to);
        }

        $query->addSelect([
            'min_price' => TicketType::selectRaw('MIN(price)')
                ->whereColumn('event_id', 'events.id'),
        ]);

        match ($request->input('sort', 'date')) {
            'date' => $query->orderBy('starts_at'),
            '-date' => $query->orderByDesc('starts_at'),
            'price' => $query->orderBy('min_price'),
            '-price' => $query->orderByDesc('min_price'),
        };

        return $query->paginate($request->input('per_page', 15));
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

    public function publish(Event $event)
    {
        $this->authorize('publish', $event);

        if ($event->status !== EventStatus::Draft) {
            abort(422, 'Опубликовать можно только черновик.');
        }

        $event->status = EventStatus::Published;
        $event->save();

        return $event;
    }

    public function cancel(Event $event)
    {
        $this->authorize('cancel', $event);

        if (!in_array($event->status, [EventStatus::Draft, EventStatus::Published], true)) {
            abort(422, 'Это мероприятие уже отменено или завершено.');
        }

        $event->status = EventStatus::Cancelled;
        $event->save();

        return $event;
    }
}
