<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Category;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(User $seller): Product
    {
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Broadcast listing',
            'description' => 'Listing used by broadcast tests.',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    private function makeMessage(User $from, User $to): Message
    {
        return Message::create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'message' => 'Hello',
            'type' => 'text',
            'is_read' => false,
        ]);
    }

    public function test_sending_a_message_dispatches_message_sent_event(): void
    {
        Event::fake([MessageSent::class]);

        $sender = User::factory()->create(['role' => 'user']);
        $recipient = User::factory()->create(['role' => 'user']);
        $product = $this->makeProduct($recipient);

        Sanctum::actingAs($sender->refresh());

        $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'message' => 'Hi there',
            'type' => 'text',
            'product_id' => $product->id,
        ])->assertStatus(201);

        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $event) => $event->message->to_user_id === $recipient->id
                && $event->message->from_user_id === $sender->id,
        );
    }

    public function test_message_sent_broadcasts_to_both_participants(): void
    {
        $from = User::factory()->create(['role' => 'user']);
        $to = User::factory()->create(['role' => 'user']);
        $event = new MessageSent($this->makeMessage($from, $to));

        $channels = array_map(fn ($channel) => (string) $channel, $event->broadcastOn());

        $this->assertSame('message.sent', $event->broadcastAs());
        $this->assertContains('private-App.Models.User.'.$from->id, $channels);
        $this->assertContains('private-App.Models.User.'.$to->id, $channels);
    }

    public function test_reacting_dispatches_message_updated_event(): void
    {
        Event::fake([MessageUpdated::class]);

        $sender = User::factory()->create(['role' => 'user']);
        $reactor = User::factory()->create(['role' => 'user']);
        $message = $this->makeMessage($sender, $reactor);

        Sanctum::actingAs($reactor->refresh());

        $this->postJson('/api/messages/'.$message->id.'/react', ['emoji' => '❤️'])
            ->assertOk();

        Event::assertDispatched(
            MessageUpdated::class,
            fn (MessageUpdated $event) => $event->messageId === $message->id
                && $event->action === 'reaction',
        );
    }

    public function test_deleting_a_message_dispatches_message_updated_event(): void
    {
        Event::fake([MessageUpdated::class]);

        $sender = User::factory()->create(['role' => 'user']);
        $recipient = User::factory()->create(['role' => 'user']);
        $message = $this->makeMessage($sender, $recipient);

        Sanctum::actingAs($sender->refresh());

        $this->deleteJson('/api/messages/'.$message->id)->assertOk();

        Event::assertDispatched(
            MessageUpdated::class,
            fn (MessageUpdated $event) => $event->messageId === $message->id
                && $event->action === 'deleted',
        );
    }

    public function test_reply_message_exposes_reply_to_payload(): void
    {
        $sender = User::factory()->create(['role' => 'user']);
        $recipient = User::factory()->create(['role' => 'user']);
        $original = $this->makeMessage($recipient, $sender);

        Sanctum::actingAs($sender->refresh());

        $response = $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'message' => 'Replying to you',
            'type' => 'text',
            'reply_to_id' => $original->id,
        ]);
        $response
            ->assertStatus(201)
            ->assertJsonPath('reply_to_id', $original->id)
            ->assertJsonPath('reply_to.id', $original->id)
            ->assertJsonPath('reply_to.from_user.id', $recipient->id);
    }

    public function test_reply_to_unknown_message_is_rejected(): void
    {
        $sender = User::factory()->create(['role' => 'user']);
        $recipient = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($sender->refresh());

        $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'message' => 'Ghost reply',
            'type' => 'text',
            'reply_to_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('reply_to_id');
    }
}
