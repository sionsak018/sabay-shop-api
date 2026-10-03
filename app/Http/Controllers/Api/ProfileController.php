<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            'province_id' => 'sometimes|nullable|exists:provinces,id',
            'district_id' => 'sometimes|nullable|exists:districts,id',
            'commune_id' => 'sometimes|nullable|exists:communes,id',
            'village_id' => 'sometimes|nullable|exists:villages,id',
            'current_password' => 'sometimes|required_with:password|current_password',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        if ($request->has('password') && $request->password != null) {
            $validated['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        } else {
            unset($validated['password']);
        }
        unset($validated['current_password']);
        unset($validated['password_confirmation']);

        if ($request->has('remove_avatar') && $request->remove_avatar == '1') {
            if ($user->avatar) {
                $this->cloudinaryService->delete($user->avatar);
                $validated['avatar'] = null;
            }
        }

        if ($request->has('remove_cover_photo') && $request->remove_cover_photo == '1') {
            if ($user->cover_photo) {
                $this->cloudinaryService->delete($user->cover_photo);
                $validated['cover_photo'] = null;
            }
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $this->cloudinaryService->delete($user->avatar);
            }
            $url = $this->cloudinaryService->upload($request->file('avatar'), 'sabay-shop/avatars');
            if ($url) {
                $validated['avatar'] = $url;
            }
        }

        if ($request->hasFile('cover_photo')) {
            if ($user->cover_photo) {
                $this->cloudinaryService->delete($user->cover_photo);
            }
            $url = $this->cloudinaryService->upload($request->file('cover_photo'), 'sabay-shop/covers');
            if ($url) {
                $validated['cover_photo'] = $url;
            }
        }

        $user->update($validated);
        Cache::forget("profile.show.{$user->id}");

        return response()->json($user->load(['province', 'district', 'commune', 'village']));
    }

    public function show($id)
    {
        $me = auth('sanctum')->user();

        // The public part of a profile is identical for every visitor, so cache
        // it (as arrays) and layer the per-viewer follow/favourite flags on top.
        $payload = Cache::remember("profile.show.{$id}", now()->addMinute(), function () use ($id) {
            $user = User::with(['province', 'district', 'commune', 'village'])->findOrFail($id);

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'cover_photo' => $user->cover_photo,
                    'about_me' => $user->about_me,
                    'account_type' => $user->account_type,
                    'rating_avg' => (float) $user->rating_avg,
                    'rating_count' => (int) $user->rating_count,
                    'province' => $user->province?->toArray(),
                    'district' => $user->district?->toArray(),
                    'commune' => $user->commune?->toArray(),
                    'village' => $user->village?->toArray(),
                    'created_at' => $user->created_at,
                ],
                'stats' => [
                    'followers_count' => $user->followers()->count(),
                    'following_count' => $user->following()->count(),
                    'ads_count' => $user->products()->where('status', 'active')->count(),
                    'rating_avg' => (float) $user->rating_avg,
                    'rating_count' => (int) $user->rating_count,
                ],
                'reviews' => Review::with(['reviewer' => fn ($q) => $q->select(User::PUBLIC_COLUMNS)])
                    ->where('seller_id', $id)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->toArray(),
                'products' => $user->products()
                    ->with(['category', 'images', 'province'])
                    ->where('status', 'active')
                    ->latest()
                    ->get()
                    ->toArray(),
            ];
        });

        $payload['is_following'] = $me
            ? $me->following()->where('following_id', $id)->exists()
            : false;

        $favorited = collect();
        if ($me && !empty($payload['products'])) {
            $favorited = DB::table('favorites')
                ->where('user_id', $me->id)
                ->whereIn('product_id', array_column($payload['products'], 'id'))
                ->pluck('product_id');
        }

        foreach ($payload['products'] as &$product) {
            $product['is_favorited'] = $favorited->contains($product['id']);
        }
        unset($product);

        return response()->json($payload);
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
