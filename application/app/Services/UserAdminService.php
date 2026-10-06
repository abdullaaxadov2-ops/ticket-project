<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserAdminService
{
    public function getList(): LengthAwarePaginator
    {
        return User::orderBy('id')->paginate(15);
    }

    public function changeRole(User $user, UserRole $role): User
    {
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function changeBlockStatus(User $user, bool $isBlocked): User
    {
        $user->is_blocked = $isBlocked;
        $user->save();

        return $user;
    }
}
