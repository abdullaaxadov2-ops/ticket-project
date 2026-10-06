<?php

namespace App\Services;

use App\Data\Events\EventFilterData;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EventSearchService
{
    public function search(?User $user, EventFilterData $filters): LengthAwarePaginator
    {
        $query = Event::query();

        if (!$user || $user->isParticipant()) {
            $query->where('status', EventStatus::Published);
        } elseif ($user->isOrganiser()) {
            $query->where(function ($q) use ($user) {
                $q->where('status', EventStatus::Published)
                    ->orWhere('organizer_id', $user->id);
            });
        }

        if ($filters->search !== null) {
            $query->whereLike('title', '%' . $filters->search . '%');
        }

        if ($filters->category_id !== null) {
            $query->where('category_id', $filters->category_id);
        }

        if ($filters->venue_id !== null) {
            $query->where('venue_id', $filters->venue_id);
        }

        if ($filters->date_from !== null) {
            $query->whereDate('starts_at', '>=', $filters->date_from);
        }

        if ($filters->date_to !== null) {
            $query->whereDate('starts_at', '<=', $filters->date_to);
        }

        $query->addSelect([
            'min_price' => TicketType::selectRaw('MIN(price)')
                ->whereColumn('event_id', 'events.id'),
        ]);

        match ($filters->sort ?? 'date') {
            'date' => $query->orderBy('starts_at'),
            '-date' => $query->orderByDesc('starts_at'),
            'price' => $query->orderBy('min_price'),
            '-price' => $query->orderByDesc('min_price'),
        };

        return $query->paginate($filters->per_page ?? 15);
    }
}
