<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $favorites = $user->favorites()
            ->with(['seller', 'category', 'images', 'province', 'commune'])
            ->withExists(['favoritedBy as is_favorited' => function($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->latest()
            ->get();
        return response()->json($favorites);
    }

    public function toggle(Request $request, $productId)
    {
        $user = $request->user();
        $product = Product::findOrFail($productId);

        if ($user->favorites()->where('product_id', $productId)->exists()) {
            $user->favorites()->detach($productId);
            $status = 'unfavorited';
        } else {
            $user->favorites()->attach($productId);
            $status = 'favorited';
        }

        return response()->json(['status' => $status]);
    }
}
