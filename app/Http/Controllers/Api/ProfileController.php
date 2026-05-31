<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CloudinaryService;

class ProfileController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'about_me' => 'sometimes|nullable|string',
            'avatar' => 'sometimes|nullable|image|max:2048',
            'cover_photo' => 'sometimes|nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $url = $this->cloudinaryService->upload($request->file('avatar'), 'sabay-shop/avatars');
            if ($url) {
                $validated['avatar'] = $url;
            }
        }

        if ($request->hasFile('cover_photo')) {
            $url = $this->cloudinaryService->upload($request->file('cover_photo'), 'sabay-shop/covers');
            if ($url) {
                $validated['cover_photo'] = $url;
            }
        }

        $user->update($validated);

        return response()->json($user);
    }

    public function show($id)
    {
        $user = \App\Models\User::findOrFail($id);
        $me = auth('sanctum')->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'cover_photo' => $user->cover_photo,
                'about_me' => $user->about_me,
                'phone' => $user->phone,
                'created_at' => $user->created_at,
            ],
            'stats' => [
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'ads_count' => $user->products()->where('status', 'active')->count(),
            ],
            'is_following' => $me ? $me->following()->where('following_id', $user->id)->exists() : false,
            'products' => $user->products()
                ->with(['category', 'images', 'province', 'commune'])
                ->withExists(['favoritedBy as is_favorited' => function($q) use ($me) {
                    if ($me) $q->where('user_id', $me->id);
                    else $q->where('user_id', 0);
                }])
                ->where('status', 'active')
                ->latest()
                ->get()
        ]);
    }

    public function stats(Request $request, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        return response()->json([
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'ads_count' => $user->products()->count(),
        ]);
    }
}
