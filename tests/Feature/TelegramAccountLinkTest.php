<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAccountLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.telegram.bot_token' => 'test-telegram-token',
            'services.telegram.bot_username' => 'sabay_shop_bot',
            'services.telegram.webhook_secret' => 'secret-123',
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    public function test_status_reports_configuration_and_link_state(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '555001', 'telegram_username' => 'kh_user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/telegram/status')
            ->assertOk()
            ->assertJson(['configured' => true, 'linked' => true, 'username' => 'kh_user']);
    }

    public function test_status_is_safe_without_a_bot_token(): void
    {
        config(['services.telegram.bot_token' => null]);
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/telegram/status')
            ->assertOk()
            ->assertJson(['configured' => false, 'linked' => false]);
    }

    public function test_link_hands_back_a_deep_link_and_pressing_start_links_the_account(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/telegram/link');

        $response->assertOk()->assertJson(['configured' => true, 'linked' => false]);

        $link = (string) $response->json('link');
        $this->assertStringContainsString('t.me/sabay_shop_bot?start=', $link);

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 424242, 'type' => 'private'],
                    'from' => ['username' => 'linked_kh'],
                    'text' => '/start ' . $query['start'],
                ],
            ])->assertOk();

        $user->refresh();
        $this->assertSame('424242', $user->telegram_chat_id);
        $this->assertSame('linked_kh', $user->telegram_username);
    }

    public function test_unlink_clears_the_connection(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '123', 'telegram_username' => 'old']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/telegram/link')
            ->assertOk()
            ->assertJson(['linked' => false]);

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_username);
    }

    public function test_webhook_rejects_requests_without_the_secret(): void
    {
        $this->postJson('/api/telegram/webhook', [
            'message' => ['chat' => ['id' => 1], 'text' => '/start anything'],
        ])->assertStatus(403);
    }

    public function test_webhook_answers_unknown_links_gracefully(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => ['chat' => ['id' => 1], 'text' => '/start expired-token'],
            ])->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_webhook_ignores_non_start_messages(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => ['chat' => ['id' => 1], 'text' => 'hello'],
            ])->assertOk();
    }

    /**
     * A chat type we do not trust must be dropped before the token is consumed,
     * otherwise a group could burn somebody's single-use link.
     */
    public function test_webhook_ignores_group_chats_without_consuming_the_token(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $link = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/telegram/link')->json('link');

        parse_str((string) parse_url((string) $link, PHP_URL_QUERY), $query);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => -100999, 'type' => 'supergroup', 'title' => 'Group'],
                    'from' => ['username' => 'someone'],
                    'text' => '/start ' . $query['start'],
                ],
            ])->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
        $this->assertNull($user->fresh()->telegram_chat_id);

        // Still usable from a private chat.
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 424242, 'type' => 'private'],
                    'from' => ['username' => 'linked_kh'],
                    'text' => '/start ' . $query['start'],
                ],
            ])->assertOk();

        $this->assertSame('424242', $user->fresh()->telegram_chat_id);
    }

    public function test_telegram_endpoints_require_authentication(): void
    {
        $this->getJson('/api/telegram/status')->assertUnauthorized();
        $this->postJson('/api/telegram/link')->assertUnauthorized();
        $this->deleteJson('/api/telegram/link')->assertUnauthorized();
    }
}
