<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Enums\EventStatus;

class EventPolicy
{
    public function view(?User $user, Event $event): bool
    {
        if ($event->status === EventStatus::Published) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $event->organizer_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isOrganiser();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->organizer_id === $user->id;
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->organizer_id === $user->id;
    }

    public function cancel(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->organizer_id === $user->id;
    }
}
