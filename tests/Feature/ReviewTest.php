<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'cars'], ['name' => 'Cars']);
    }

    private function makeProduct(User $seller, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $this->category()->id,
            'title' => 'Listing '.Str::random(6),
            'description' => 'A product used for review tests.',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ], $overrides));
    }

    private function purchase(User $buyer, Product ...$products): Order
    {
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $products[0]->seller_id,
            'total_amount' => collect($products)->sum('price'),
            'status' => 'pending',
        ]);

        foreach ($products as $product) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'price_at_purchase' => $product->price,
                'quantity' => 1,
            ]);
        }

        return $order;
    }

    public function test_purchaser_can_review_a_product_and_rating_is_recomputed(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $product = $this->makeProduct($seller);
        $this->purchase($reviewer, $product);

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'Fast and reliable seller.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('rating_avg', 5)
            ->assertJsonPath('rating_count', 1);

        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $reviewer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);

        $seller->refresh();
        $this->assertEquals(5.0, $seller->rating_avg);
        $this->assertEquals(1, $seller->rating_count);
    }

    public function test_review_requires_a_complete_description(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $product = $this->makeProduct($seller);
        $this->purchase($reviewer, $product);

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
            'comment' => 'Too short',
        ])->assertStatus(422)->assertJsonValidationErrors('comment');

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
        ])->assertStatus(422)->assertJsonValidationErrors('comment');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_cannot_review_a_product_they_did_not_purchase(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $product = $this->makeProduct($seller);

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'I never bought this item.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_cannot_review_their_own_product(): void
    {
        $seller = User::factory()->create();
        $product = $this->makeProduct($seller);
        $this->purchase($seller, $product);

        Sanctum::actingAs($seller->refresh());

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
            'comment' => 'Reviewing my own listing.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_can_only_review_a_product_once(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $product = $this->makeProduct($seller);
        $this->purchase($reviewer, $product);

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'First and only review.',
        ])->assertStatus(201);

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 1,
            'comment' => 'Trying to review again.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_user_can_review_two_different_products_from_the_same_seller(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $first = $this->makeProduct($seller);
        $second = $this->makeProduct($seller);
        $this->purchase($reviewer, $first, $second);

        Sanctum::actingAs($reviewer->refresh());

        $this->postJson('/api/reviews', [
            'product_id' => $first->id,
            'rating' => 5,
            'comment' => 'Loved the first product.',
        ])->assertStatus(201);

        $this->postJson('/api/reviews', [
            'product_id' => $second->id,
            'rating' => 4,
            'comment' => 'Second product was good too.',
        ])->assertStatus(201);

        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_guest_cannot_submit_a_review(): void
    {
        $seller = User::factory()->create();
        $product = $this->makeProduct($seller);

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'A guest trying to review.',
        ])->assertStatus(401);
    }

    public function test_seller_reviews_endpoint_returns_aggregate_and_paginated_list(): void
    {
        $seller = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstProduct = $this->makeProduct($seller);
        $secondProduct = $this->makeProduct($seller);
        $this->purchase($first, $firstProduct);
        $this->purchase($second, $secondProduct);

        foreach ([[$first, $firstProduct, 5], [$second, $secondProduct, 3]] as [$reviewer, $product, $rating]) {
            Sanctum::actingAs($reviewer->refresh());
            $this->postJson('/api/reviews', [
                'product_id' => $product->id,
                'rating' => $rating,
                'comment' => 'A detailed enough comment.',
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

    public function test_product_detail_exposes_seller_rating_and_review_eligibility(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();
        $product = $this->makeProduct($seller);

        $this->purchase($reviewer, $product);
        Sanctum::actingAs($reviewer->refresh());

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('can_review', true)
            ->assertJsonPath('my_review', null);

        $this->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
            'comment' => 'A solid experience overall.',
        ])->assertStatus(201);

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('seller.rating_avg', 4)
            ->assertJsonPath('seller.rating_count', 1)
            ->assertJsonPath('can_review', false)
            ->assertJsonPath('my_review.rating', 4);
    }

    public function test_review_submission_is_rate_limited(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $products = collect(range(1, 6))->map(fn () => $this->makeProduct($seller))->all();
        $this->purchase($buyer, ...$products);

        Sanctum::actingAs($buyer->refresh());

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reviews', [
                'product_id' => $products[$i]->id,
                'rating' => 5,
                'comment' => 'Review number '.$i.' is long enough.',
            ])->assertStatus(201);
        }

        $this->postJson('/api/reviews', [
            'product_id' => $products[5]->id,
            'rating' => 5,
            'comment' => 'The sixth review should be blocked.',
        ])->assertStatus(429);
    }
}
