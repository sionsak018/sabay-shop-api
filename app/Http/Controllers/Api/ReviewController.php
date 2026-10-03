<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

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
     * Create or update the authenticated user's review for a seller.
     */
    public function store(Request $request)
    {
        $me = $request->user();

        $validated = $request->validate([
            'seller_id' => 'required|integer|exists:users,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ((int) $validated['seller_id'] === $me->id) {
            return response()->json(['message' => 'You cannot review yourself.'], 422);
        }

        $review = Review::updateOrCreate(
            ['reviewer_id' => $me->id, 'seller_id' => $validated['seller_id']],
            [
                'product_id' => $validated['product_id'] ?? null,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        $seller = User::findOrFail($validated['seller_id']);

        return response()->json([
            'message' => 'Review submitted.',
            'review' => $review->fresh(['reviewer']),
            'rating_avg' => (float) $seller->rating_avg,
            'rating_count' => (int) $seller->rating_count,
        ], 201);
    }
}
