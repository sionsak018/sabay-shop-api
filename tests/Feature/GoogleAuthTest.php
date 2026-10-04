<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.google.client_id' => 'test-google-client']);
    }

    private function fakeGoogleToken(array $overrides = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(array_merge([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test-google-client',
                'sub' => 'google-user-123',
                'email' => 'new-customer@example.com',
                'email_verified' => 'true',
                'name' => 'New Customer',
                'picture' => 'https://lh3.googleusercontent.com/a/example',
                'exp' => time() + 3600,
            ], $overrides)),
        ]);
    }

    public function test_new_google_user_is_created_and_receives_a_token(): void
    {
        $this->fakeGoogleToken();

        $response = $this->postJson('/api/auth/google', ['credential' => 'valid-google-token']);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $user = User::where('email', 'new-customer@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('google-user-123', $user->google_id);
        $this->assertSame('New Customer', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertIsString($response->json('token'));
    }

    public function test_existing_email_account_is_linked_to_google(): void
    {
        $existing = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-existing-456',
            'email' => 'existing@example.com',
            'name' => 'Google Display Name',
        ]);

        $response = $this->postJson('/api/auth/google', ['credential' => 'valid-google-token']);

        $response->assertOk();

        $existing->refresh();

        $this->assertSame('google-existing-456', $existing->google_id);
        $this->assertNotNull($existing->email_verified_at);
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_google_id_already_linked_logs_the_same_user_in(): void
    {
        $user = User::create([
            'name' => 'Linked User',
            'email' => 'linked@example.com',
            'password' => bcrypt('password123'),
            'google_id' => 'google-linked-789',
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-linked-789',
            'email' => 'linked@example.com',
        ]);

        $response = $this->postJson('/api/auth/google', ['credential' => 'valid-google-token']);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_google_rejects_when_credential_missing(): void
    {
        $this->postJson('/api/auth/google', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('credential');
    }

    public function test_google_rejects_when_tokeninfo_fails(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400),
        ]);

        $this->postJson('/api/auth/google', ['credential' => 'bad-token'])
            ->assertStatus(401);
    }

    public function test_google_rejects_audience_mismatch(): void
    {
        $this->fakeGoogleToken(['aud' => 'some-other-client']);

        $this->postJson('/api/auth/google', ['credential' => 'wrong-aud-token'])
            ->assertStatus(401);

        $this->assertDatabaseMissing('users', ['email' => 'new-customer@example.com']);
    }

    public function test_google_rejects_unverified_email(): void
    {
        $this->fakeGoogleToken(['email_verified' => false]);

        $this->postJson('/api/auth/google', ['credential' => 'unverified-token'])
            ->assertStatus(401);
    }

    public function test_google_rejects_when_client_id_is_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->postJson('/api/auth/google', ['credential' => 'anything'])
            ->assertStatus(401);
    }

}
