<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordOtpService
{
    public const TTL_MINUTES = 2;
    public const INTENT_TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    /**
     * Create a fresh 6-digit OTP for the user and store only its hash.
     */
    public function generate(User $user): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($user), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $otp;
    }

    public function record(User $user): ?array
    {
        return Cache::get($this->key($user));
    }

    public function forget(User $user): void
    {
        Cache::forget($this->key($user));
    }

    public function markFailed(User $user, array $record): void
    {
        $record['attempts'] = ($record['attempts'] ?? 0) + 1;

        Cache::put($this->key($user), $record, now()->addMinutes(self::TTL_MINUTES));
    }

    public function key(User $user): string
    {
        return 'password_otp:user:' . $user->id;
    }

    /**
     * A short random token used as the deep-link parameter for a recovery.
     */
    public function newToken(): string
    {
        return Str::random(40);
    }

    /**
     * Mint the secret that only the browser which started this reset will hold.
     * Without it, a leaked deep link or code cannot finish the reset.
     */
    public function newIntent(User $user): string
    {
        $token = Str::random(40);

        Cache::put(
            $this->intentKey($user),
            hash('sha256', $token),
            now()->addMinutes(self::INTENT_TTL_MINUTES)
        );

        return $token;
    }

    public function intentMatches(User $user, ?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $stored = Cache::get($this->intentKey($user));

        return is_string($stored) && hash_equals($stored, hash('sha256', $token));
    }

    public function forgetIntent(User $user): void
    {
        Cache::forget($this->intentKey($user));
    }

    protected function intentKey(User $user): string
    {
        return 'password_intent:user:' . $user->id;
    }
}
