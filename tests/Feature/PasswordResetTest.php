<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordOtpService;
use App\Services\TelegramService;
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
    private ?string $resetToken = null;

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

    /**
     * Start a reset and keep the reset_token the API handed back.
     */
    private function forgot(string $login = '012345678')
    {
        $response = $this->postJson('/api/password/forgot', ['login' => $login]);
        $this->resetToken = $response->json('reset_token');

        return $response;
    }

    private function reset(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/password/reset', array_merge([
            'login' => '012345678',
            'otp' => '000000',
            'reset_token' => $this->resetToken,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ], $overrides));
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

    /**
     * With no Telegram linked there is nothing that proves the requester owns this
     * phone number, so no link is issued at all. Otherwise anybody holding the
     * number could link their own Telegram and reset the password.
     */
    public function test_unlinked_account_is_refused_a_recovery_link(): void
    {
        $user = $this->user();

        $this->forgot()
            ->assertStatus(422)
            ->assertJson(['method' => 'telegram_not_linked']);

        $response = $this->postJson('/api/password/forgot', ['login' => '012345678']);

        $this->assertNull($response->json('link'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
        $this->assertNull($user->fresh()->telegram_chat_id);
        $this->assertNull(Cache::get((new PasswordOtpService())->key($user)));
    }

    /**
     * A recovery link is only ever honoured from the chat that was linked while
     * the customer was signed in.
     */
    public function test_a_recovery_link_is_refused_from_any_other_chat(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);

        // A link minted before the account had a Telegram attached.
        $this->telegramLinkFor($user);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-123')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 777888, 'type' => 'private'],
                    'from' => ['username' => 'attacker'],
                    'text' => '/start ' . $this->linkToken,
                ],
            ])->assertOk();

        $this->assertSame('555001', $user->fresh()->telegram_chat_id, 'The linked chat must not change.');
        $this->assertNull(Cache::get((new PasswordOtpService())->key($user)), 'No OTP may be generated.');
        // The refusal is sent, but it must not contain a usable code.
        $this->assertNull($this->sentCode(), 'No 6-digit code may be sent to the wrong chat.');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && ($request['chat_id'] ?? null) === '777888'
            && !preg_match('/\b\d{6}\b/', (string) ($request['text'] ?? '')));
    }

    private string $linkToken = '';

    private function telegramLinkFor(User $user): string
    {
        $this->linkToken = (new PasswordOtpService())->newToken();
        app(TelegramService::class)->rememberLink($this->linkToken, [
            'type' => 'recovery',
            'user_id' => $user->id,
        ]);

        return $this->linkToken;
    }

    public function test_reset_with_a_valid_code_changes_the_password_and_revokes_tokens(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);
        $user->createToken('existing-session');

        $this->forgot()->assertOk();
        $code = $this->sentCode();

        $this->reset(['otp' => $code])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull(Cache::get((new PasswordOtpService())->key($user)));
    }

    /**
     * A leaked code must not be usable from another browser: only the session
     * that requested the reset holds the matching reset_token.
     */
    public function test_a_valid_code_with_the_wrong_reset_token_changes_nothing(): void
    {
        $user = $this->user(['telegram_chat_id' => '555001']);
        $original = $user->password;

        $this->forgot()->assertOk();
        $code = $this->sentCode();

        $this->reset(['otp' => $code, 'reset_token' => 'somebody-elses-token'])->assertStatus(422);

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_reset_requires_the_reset_token(): void
    {
        $this->user(['telegram_chat_id' => '555001']);
        $this->forgot()->assertOk();

        $this->postJson('/api/password/reset', [
            'login' => '012345678',
            'otp' => $this->sentCode(),
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422)->assertJsonValidationErrors('reset_token');
    }

    public function test_reset_works_with_an_email_identifier(): void
    {
        $user = $this->user(['email' => 'reset@example.com', 'telegram_chat_id' => '555001']);

        $this->forgot($user->email)->assertOk();
        $code = $this->sentCode();

        $this->reset([
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
        $this->forgot()->assertOk();

        $this->reset()->assertStatus(422);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_too_many_wrong_codes_lock_the_reset(): void
    {
        $this->user(['telegram_chat_id' => '555001']);
        $this->forgot()->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->reset()->assertStatus(422);
        }

        $this->reset()->assertStatus(429);
    }

    public function test_reset_requires_matching_confirmation(): void
    {
        $this->user(['telegram_chat_id' => '555001']);
        $this->forgot()->assertOk();
        $code = $this->sentCode();

        $this->reset([
            'otp' => $code,
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
