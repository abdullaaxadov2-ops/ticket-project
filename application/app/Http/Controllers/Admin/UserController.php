<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserBlockRequest;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);
        return User::paginate(15);
    }
    public function changeRole(ChangeUserRoleRequest $request, User $user)
    {
        $this->authorize('manage', $user);
        $user->role = $request->role;
        $user->save();

        return $user;
    }

    public function changeBlockStatus(ChangeUserBlockRequest $request, User $user)
    {
        $this->authorize('manage', $user);
        $user->is_blocked = $request->is_blocked;
        $user->save();

        return $user;
    }
}
