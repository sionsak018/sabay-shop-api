<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        Cache::flush();
        config([
            'services.telegram.bot_token' => 'test-telegram-token',
            'services.telegram.bot_username' => 'sabay_shop_bot',
            'services.telegram.webhook_secret' => 'secret-123',
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Customer',
            'phone' => '012 345 678',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ], $overrides);
    }

    /**
     * Start a sign-up, press Start through the webhook, and return the code
     * that the bot sent to Telegram.
     */
    private array $start = [];

    /**
     * Start a sign-up, press Start through the webhook, and return the code the
     * bot sent to Telegram. The matching verify_token is kept in $this->start.
     */
    private function codeFromTelegram(string $phone = '012345678'): string
    {
        $this->start = (array) $this->postJson('/api/register/start', $this->payload(['phone' => $phone]))->json();

        $link = (string) $this->start['link'];
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 555001, 'type' => 'private'],
                    'from' => ['username' => 'new_kh'],
                    'text' => '/start ' . $query['start'],
                ],
            ])->assertOk();

        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            if (str_contains($request->url(), 'sendMessage')
                && preg_match('/\b(\d{6})\b/', (string) ($request['text'] ?? ''), $m)) {
                $code = $m[1];
            }

            return true;
        });

        return (string) $code;
    }

    private function verify(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/register/verify', array_merge([
            'phone' => '012345678',
            'otp' => '000000',
            'verify_token' => $this->start['verify_token'] ?? null,
        ], $overrides));
    }

    public function test_start_returns_a_telegram_link(): void
    {
        $response = $this->postJson('/api/register/start', $this->payload());

        $response->assertOk()
            ->assertJson(['method' => 'telegram_link', 'bot_username' => 'sabay_shop_bot']);

        $this->assertStringContainsString('t.me/sabay_shop_bot?start=', (string) $response->json('link'));
        $this->assertSame(0, User::count());
    }

    public function test_start_rejects_a_duplicate_phone(): void
    {
        User::factory()->create(['email' => null, 'phone' => '012345678']);

        $this->postJson('/api/register/start', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_start_requires_a_matching_confirmation(): void
    {
        $this->postJson('/api/register/start', $this->payload(['password_confirmation' => 'different']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_start_is_unavailable_when_telegram_is_not_configured(): void
    {
        config(['services.telegram.bot_token' => null]);

        $this->postJson('/api/register/start', $this->payload())->assertStatus(503);
    }

    public function test_verified_code_creates_the_account_and_links_telegram(): void
    {
        $code = $this->codeFromTelegram();

        $this->assertNotNull($code);

        $response = $this->verify(['otp' => $code]);

        $response->assertStatus(201)->assertJsonStructure(['user' => ['id', 'name', 'phone'], 'token']);

        $user = User::first();
        $this->assertSame('New Customer', $user->name);
        $this->assertSame('012345678', $user->phone);
        $this->assertNull($user->email);
        $this->assertSame('555001', $user->telegram_chat_id);
        $this->assertSame('new_kh', $user->telegram_username);
        $this->assertTrue(Hash::check('secret-pass-123', $user->password));
        $this->assertNotNull($user->tokens()->first());
    }

    public function test_the_new_account_can_sign_in_with_its_phone(): void
    {
        $code = $this->codeFromTelegram();
        $this->verify(['otp' => $code])->assertStatus(201);

        $this->postJson('/api/login', ['email' => '012345678', 'password' => 'secret-pass-123'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->codeFromTelegram();

        $this->verify()->assertStatus(422);

        $this->assertSame(0, User::count());
    }

    public function test_too_many_wrong_codes_lock_the_signup(): void
    {
        $this->codeFromTelegram();

        for ($i = 0; $i < 5; $i++) {
            $this->verify()->assertStatus(422);
        }

        $this->verify()->assertStatus(429);
    }

    public function test_verify_without_starting_is_rejected(): void
    {
        $this->postJson('/api/register/verify', [
            'phone' => '012345678',
            'otp' => '123456',
            'verify_token' => 'made-up',
        ])->assertStatus(422);
    }

    /**
     * A leaked deep link plus a leaked code must still not be enough to create
     * the account: the sign-up can only be finished by the browser that started it.
     */
    public function test_a_correct_code_with_the_wrong_verify_token_cannot_create_the_account(): void
    {
        $code = $this->codeFromTelegram();

        $this->assertNotEmpty($code);

        $this->verify(['otp' => $code, 'verify_token' => 'somebody-elses-token'])
            ->assertStatus(422);

        $this->assertSame(0, User::count());
    }

    public function test_verify_requires_the_verify_token(): void
    {
        $this->codeFromTelegram();

        $this->postJson('/api/register/verify', ['phone' => '012345678', 'otp' => '123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('verify_token');
    }

    /**
     * A code posted from a group would be readable by everyone in it, so a
     * non-private chat must be ignored without even consuming the link.
     */
    public function test_a_code_is_never_sent_into_a_group_chat(): void
    {
        $start = $this->postJson('/api/register/start', $this->payload())->json();
        parse_str((string) parse_url((string) $start['link'], PHP_URL_QUERY), $query);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => -1001234567890, 'type' => 'supergroup', 'title' => 'Some Group'],
                    'from' => ['username' => 'someone'],
                    'text' => '/start ' . $query['start'],
                ],
            ])->assertOk();

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'sendMessage');
        });

        // The token was not burned, so the real customer can still use it.
        $this->assertSame($start['verify_token'], Cache::get('signup_pending:' . $query['start'])['verify_token']);
    }
}
