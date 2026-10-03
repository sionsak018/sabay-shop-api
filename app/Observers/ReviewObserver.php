<?php

namespace App\Observers;

use App\Models\Review;

class ReviewObserver
{
    public function saved(Review $review): void
    {
        $review->seller?->refreshRating();
    }

    public function deleted(Review $review): void
    {
        $review->seller?->refreshRating();
    }
}
