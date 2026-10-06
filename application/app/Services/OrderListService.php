<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderListService
{
    public function list(User $user): LengthAwarePaginator
    {
        $query = Order::with('items')->latest();

        if ($user->isOrganiser()) {
            $query->whereHas('event', fn ($q) => $q->where('organizer_id', $user->id));
        } elseif (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return $query->paginate(15);
    }
}
