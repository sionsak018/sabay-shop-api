<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicPiiTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(User $seller): Product
    {
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Product',
            'description' => 'Description',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    public function test_favorites_hide_seller_pii(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $product = $this->makeProduct($seller);
        $buyer->favorites()->attach($product->id);
        Sanctum::actingAs($buyer);

        $sellerJson = $this->getJson('/api/favorites')->assertOk()->json('0.seller');

        $this->assertNotNull($sellerJson);
        $this->assertArrayNotHasKey('email', $sellerJson);
        $this->assertArrayNotHasKey('phone', $sellerJson);
    }

    public function test_followers_and_following_hide_pii(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $b->following()->attach($a->id);

        Sanctum::actingAs($a);
        $follower = $this->getJson("/api/followers/{$a->id}")->assertOk()->json('0');
        $this->assertArrayNotHasKey('email', $follower);
        $this->assertArrayNotHasKey('phone', $follower);

        Sanctum::actingAs($b);
        $followed = $this->getJson("/api/following/{$b->id}")->assertOk()->json('0');
        $this->assertArrayNotHasKey('email', $followed);
        $this->assertArrayNotHasKey('phone', $followed);
    }

    public function test_messages_hide_user_pii(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        Message::create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'message' => 'hello',
            'type' => 'text',
        ]);
        Sanctum::actingAs($from);

        $message = $this->getJson('/api/messages')->assertOk();

        $this->assertArrayNotHasKey('email', $message->json('0.from_user'));
        $this->assertArrayNotHasKey('phone', $message->json('0.from_user'));
        $this->assertArrayNotHasKey('email', $message->json('0.to_user'));
        $this->assertArrayNotHasKey('phone', $message->json('0.to_user'));
    }
}
