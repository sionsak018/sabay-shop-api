<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_review_seller_and_rating_is_recomputed(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'seller_id' => $seller->id,
            'rating' => 5,
            'comment' => 'Fast and reliable.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('rating_avg', 5)
            ->assertJsonPath('rating_count', 1);

        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $reviewer->id,
            'seller_id' => $seller->id,
            'rating' => 5,
        ]);

        $seller->refresh();
        $this->assertEquals(5.0, $seller->rating_avg);
        $this->assertEquals(1, $seller->rating_count);
    }

    public function test_user_cannot_review_themselves(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user->refresh());

        $this->postJson('/api/reviews', [
            'seller_id' => $user->id,
            'rating' => 4,
        ])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_guest_cannot_submit_a_review(): void
    {
        $seller = User::factory()->create();

        $this->postJson('/api/reviews', [
            'seller_id' => $seller->id,
            'rating' => 5,
        ])->assertStatus(401);
    }

    public function test_resubmitting_updates_the_existing_review(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'seller_id' => $seller->id,
            'rating' => 5,
        ])->assertStatus(201);

        $this->postJson('/api/reviews', [
            'seller_id' => $seller->id,
            'rating' => 2,
            'comment' => 'Changed my mind.',
        ])->assertStatus(201)
            ->assertJsonPath('rating_count', 1)
            ->assertJsonPath('rating_avg', 2);

        $this->assertDatabaseCount('reviews', 1);

        $seller->refresh();
        $this->assertEquals(2.0, $seller->rating_avg);
        $this->assertEquals(1, $seller->rating_count);
    }

    public function test_seller_reviews_endpoint_returns_aggregate_and_paginated_list(): void
    {
        $seller = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        foreach ([[$first, 5], [$second, 3]] as [$reviewer, $rating]) {
            Sanctum::actingAs($reviewer->refresh());
            $this->postJson('/api/reviews', [
                'seller_id' => $seller->id,
                'rating' => $rating,
            ])->assertStatus(201);
        }

        $this->getJson("/api/reviews/seller/{$seller->id}")
            ->assertStatus(200)
            ->assertJsonPath('rating_count', 2)
            ->assertJsonPath('rating_avg', 4)
            ->assertJsonCount(2, 'reviews.data');
    }

    public function test_public_stats_endpoint_returns_counts(): void
    {
        User::factory()->count(3)->create();
        Category::create(['name' => 'Cars', 'slug' => 'cars']);

        $this->getJson('/api/stats/public')
            ->assertStatus(200)
            ->assertJsonPath('total_users', 3)
            ->assertJsonPath('total_categories', 1)
            ->assertJsonPath('total_products', 0);
    }

    public function test_product_detail_exposes_seller_rating(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Rating exposure',
            'description' => 'Check the seller payload.',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);

        Sanctum::actingAs($reviewer->refresh());
        $this->postJson('/api/reviews', [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'rating' => 4,
        ])->assertStatus(201);

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('seller.rating_avg', 4)
            ->assertJsonPath('seller.rating_count', 1);
    }

    public function test_review_submission_is_rate_limited(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();

        Sanctum::actingAs($reviewer->refresh());

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reviews', [
                'seller_id' => $seller->id,
                'rating' => 5,
            ])->assertStatus(201);
        }

        $this->postJson('/api/reviews', [
            'seller_id' => $seller->id,
            'rating' => 5,
        ])->assertStatus(429);
    }
}
