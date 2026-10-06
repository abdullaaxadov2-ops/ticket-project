<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    public function changePassword(User $user, string $newPassword): void
    {
        $user->changePassword($newPassword);
    }

    public function changeName(User $user, string $name): User
    {
        $user->name = $name;
        $user->save();

        return $user;
    }
}
