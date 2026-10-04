<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin()
            || $order->user_id === $user->id
            || $order->event->organizer_id === $user->id;
    }
}
