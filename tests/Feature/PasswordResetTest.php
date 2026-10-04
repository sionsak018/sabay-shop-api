<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Customer',
            'phone' => '012345678',
            'email' => null,
        ], $attributes));
    }

    /**
     * Grab the 6-digit code the bot sent to Telegram.
     */
    private function sentCode(): ?string
    {
        $code = null;

        Http::assertSent(function ($request) use (&$code) {
            if (str_contains($request->url(), 'sendMessage')
                && preg_match('/\b(\d{6})\b/', (string) ($request['text'] ?? ''), $m)) {
                $code = $m[1];
            }

            return true;
        });

        return $code;
    }

    public function test_google_account_is_told_to_verify_with_google(): void
    {
        $user = $this->user(['google_id' => 'google-123']);

        $this->postJson('/api/password/forgot', ['login' => $user->phone])
            ->assertOk()
            ->assertJson(['method' => 'google']);
    }

    public function test_linked_account_receives_the_code_straight_away(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);

        $this->postJson('/api/password/forgot', ['login' => '012345678'])
            ->assertOk()
            ->assertJson(['method' => 'otp', 'channel' => 'telegram']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && ($request['chat_id'] ?? null) === '555001');

        $this->assertNotNull($this->sentCode());
        $this->assertNotNull(Cache::get((new PasswordOtpService())->key($user)));
    }

    public function test_unlinked_account_gets_a_deep_link_that_links_and_sends_the_code(): void
    {
        $user = $this->user();

        $link = $this->postJson('/api/password/forgot', ['login' => '012345678'])
            ->assertOk()
            ->assertJson(['method' => 'telegram_link'])
            ->json('link');

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 777888],
                    'from' => ['username' => 'reset_kh'],
                    'text' => '/start ' . $query['start'],
                ],
            ])->assertOk();

        $this->assertSame('777888', $user->fresh()->telegram_chat_id);
        $this->assertNotNull($this->sentCode());
    }

    public function test_reset_with_a_valid_code_changes_the_password_and_revokes_tokens(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);
        $user->createToken('existing-session');

        $this->postJson('/api/password/forgot', ['login' => '012345678'])->assertOk();
        $code = $this->sentCode();

        $this->postJson('/api/password/reset', [
            'login' => '012345678',
            'otp' => $code,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull(Cache::get((new PasswordOtpService())->key($user)));
    }

    public function test_reset_works_with_an_email_identifier(): void
    {
        $user = $this->user(['email' => 'reset@example.com', 'telegram_chat_id' => '555001']);

        $this->postJson('/api/password/forgot', ['login' => $user->email])->assertOk();
        $code = $this->sentCode();

        $this->postJson('/api/password/reset', [
            'login' => $user->email,
            'otp' => $code,
            'password' => 'email-reset-pass',
            'password_confirmation' => 'email-reset-pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('email-reset-pass', $user->fresh()->password));
    }

    public function test_unknown_identifier_returns_not_found(): void
    {
        $this->postJson('/api/password/forgot', ['login' => 'nobody@example.com'])
            ->assertStatus(404)
            ->assertJson(['method' => 'none']);
    }

    public function test_reset_is_unavailable_when_telegram_is_not_configured(): void
    {
        config(['services.telegram.bot_token' => null]);
        $this->user();

        $this->postJson('/api/password/forgot', ['login' => '012345678'])->assertStatus(503);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);
        $this->postJson('/api/password/forgot', ['login' => '012345678'])->assertOk();

        $this->postJson('/api/password/reset', [
            'login' => '012345678',
            'otp' => '000000',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_too_many_wrong_codes_lock_the_reset(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);
        $this->postJson('/api/password/forgot', ['login' => '012345678'])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/password/reset', [
                'login' => '012345678',
                'otp' => '000000',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])->assertStatus(422);
        }

        $this->postJson('/api/password/reset', [
            'login' => '012345678',
            'otp' => '000000',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(429);
    }

    public function test_reset_requires_matching_confirmation(): void
    {
        $this->user(['telegram_chat_id' => '555001']);
        $this->postJson('/api/password/forgot', ['login' => '012345678'])->assertOk();
        $code = $this->sentCode();

        $this->postJson('/api/password/reset', [
            'login' => '012345678',
            'otp' => $code,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'different-pass',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_google_only_account_cannot_set_a_password(): void
    {
        $user = $this->user(['google_id' => 'google-123', 'password' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile', [
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertNull($user->fresh()->password);
    }

    public function test_password_account_can_change_its_password_without_the_old_one(): void
    {
        $user = $this->user();
        $original = $user->password;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile', [
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNotSame($original, $user->password);
        $this->assertTrue(Hash::check('brand-new-pass', $user->password));
    }

    public function test_profile_reports_that_a_google_account_is_passwordless(): void
    {
        $user = $this->user(['google_id' => 'google-123', 'password' => null]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson(['has_password' => false, 'auth_provider' => 'google']);
    }
}
