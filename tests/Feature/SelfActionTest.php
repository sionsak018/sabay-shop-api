<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(User $seller): Product
    {
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Self action listing',
            'description' => 'Listing used by the self-action tests.',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_favorite_their_own_product(): void
    {
        $seller = User::factory()->create(['role' => 'user']);
        $product = $this->makeProduct($seller);

        Sanctum::actingAs($seller->refresh());

        $this->postJson('/api/favorites/'.$product->id)->assertStatus(403);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_user_can_favorite_another_users_product(): void
    {
        $seller = User::factory()->create(['role' => 'user']);
        $buyer = User::factory()->create(['role' => 'user']);
        $product = $this->makeProduct($seller);

        Sanctum::actingAs($buyer->refresh());

        $this->postJson('/api/favorites/'.$product->id)->assertOk();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_user_cannot_message_themselves(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $product = $this->makeProduct($other);

        Sanctum::actingAs($user->refresh());

        $this->postJson('/api/messages', [
            'to_user_id' => $user->id,
            'message' => 'Hello me',
            'type' => 'text',
            'product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_user_can_message_another_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $product = $this->makeProduct($other);

        Sanctum::actingAs($user->refresh());

        $this->postJson('/api/messages', [
            'to_user_id' => $other->id,
            'message' => 'Hello seller',
            'type' => 'text',
            'product_id' => $product->id,
        ])->assertStatus(201);
    }
}
