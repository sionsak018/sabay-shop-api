<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function toggle(Request $request, $userId)
    {
        $userToFollow = User::findOrFail($userId);
        $me = $request->user();

        if ($me->id === $userToFollow->id) {
            return response()->json(['message' => 'You cannot follow yourself'], 400);
        }

        if ($me->following()->where('following_id', $userId)->exists()) {
            $me->following()->detach($userId);
            $status = 'unfollowed';
        } else {
            $me->following()->attach($userId);
            $status = 'followed';
        }

        return response()->json(['status' => $status]);
    }

    public function followers(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        return response()->json($user->followers);
    }

    public function following(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        return response()->json($user->following);
    }
}
