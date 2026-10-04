<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReviewController extends Controller
{
    /**
     * Public list of reviews received by a seller, plus the aggregate rating.
     */
    public function seller(Request $request, $userId)
    {
        $seller = User::findOrFail($userId);

        $reviews = Review::with(['reviewer' => fn ($q) => $q->select(User::PUBLIC_COLUMNS)])
            ->where('seller_id', $userId)
            ->latest()
            ->paginate(10);

        return response()->json([
            'rating_avg' => (float) $seller->rating_avg,
            'rating_count' => (int) $seller->rating_count,
            'reviews' => $reviews,
        ]);
    }

    /**
     * Create the authenticated user's single review for a purchased product.
     */
    public function store(Request $request)
    {
        $me = $request->user();

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if ((int) $product->seller_id === $me->id) {
            return response()->json(['message' => 'You cannot review your own product.'], 422);
        }

        $hasPurchased = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($query) => $query->where('buyer_id', $me->id))
            ->exists();

        if (! $hasPurchased) {
            return response()->json(['message' => 'You can only review a product you have purchased.'], 422);
        }

        $alreadyReviewed = Review::where('reviewer_id', $me->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json(['message' => 'You have already reviewed this product.'], 422);
        }

        $review = Review::create([
            'reviewer_id' => $me->id,
            'seller_id' => $product->seller_id,
            'product_id' => $product->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        $seller = $product->seller;

        Cache::forget("product.{$product->id}");
        Cache::forget("profile.show.{$product->seller_id}");
        Product::flushSellerCache($product->seller_id);
        Product::flushListingsCache();

        return response()->json([
            'message' => 'Review submitted.',
            'review' => $review->fresh(['reviewer']),
            'rating_avg' => (float) $seller->rating_avg,
            'rating_count' => (int) $seller->rating_count,
        ], 201);
    }
}
