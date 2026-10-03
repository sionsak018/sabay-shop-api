<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageReactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Message}
     */
    private function conversation(): array
    {
        $seller = User::factory()->create(['role' => 'user']);
        $buyer = User::factory()->create(['role' => 'user']);
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Reaction test listing',
            'description' => 'Listing used by the message reaction tests.',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);

        $message = Message::create([
            'from_user_id' => $seller->id,
            'to_user_id' => $buyer->id,
            'product_id' => $product->id,
            'message' => 'Hello',
            'type' => 'text',
        ]);

        return [$seller, $buyer, $message];
    }

    public function test_user_cannot_react_to_their_own_message(): void
    {
        [$seller, , $message] = $this->conversation();

        Sanctum::actingAs($seller->refresh());

        $this->postJson('/api/messages/'.$message->id.'/react', ['emoji' => '❤️'])
            ->assertStatus(403);

        $this->assertDatabaseCount('message_reactions', 0);
    }

    public function test_user_can_react_to_someone_elses_message(): void
    {
        [, $buyer, $message] = $this->conversation();

        Sanctum::actingAs($buyer->refresh());

        $this->postJson('/api/messages/'.$message->id.'/react', ['emoji' => '❤️'])
            ->assertOk();

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $buyer->id,
            'emoji' => '❤️',
        ]);
    }
}
