<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PhoneVerificationService
{
    public const TTL_MINUTES = 10;
    public const LINK_TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    /**
     * Park a sign-up until its Telegram code has been confirmed.
     */
    public function begin(array $payload): string
    {
        $token = Str::random(40);

        Cache::put($this->pendingKey($token), [
            'name' => $payload['name'],
            'phone' => $payload['phone'],
            'password' => Hash::make($payload['password']),
        ], now()->addMinutes(self::LINK_TTL_MINUTES));

        Cache::put($this->phoneKey($this->digits($payload['phone'])), $token, now()->addMinutes(self::LINK_TTL_MINUTES));

        return $token;
    }

    public function pending(?string $token): ?array
    {
        return $token ? Cache::get($this->pendingKey($token)) : null;
    }

    /**
     * Issue the 6-digit code once the customer has pressed Start in Telegram.
     */
    public function issueCode(string $phone, string $token, string $chatId, ?string $username = null): string
    {
        $digits = $this->digits($phone);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->otpKey($digits), [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'token' => $token,
        ], now()->addMinutes(self::TTL_MINUTES));

        Cache::put($this->chatKey($digits), [
            'chat_id' => $chatId,
            'username' => $username,
        ], now()->addMinutes(self::LINK_TTL_MINUTES));

        return $code;
    }

    /**
     * The Telegram chat that confirmed this sign-up, if any.
     */
    public function chat(string $phone): ?array
    {
        return Cache::get($this->chatKey($this->digits($phone)));
    }

    /**
     * Check a submitted code. Returns ['ok' => bool, 'message' => string] and,
     * on success, the parked sign-up plus the Telegram chat to link.
     */
    public function attempt(string $phone, string $otp): array
    {
        $digits = $this->digits($phone);
        $record = Cache::get($this->otpKey($digits));

        if (!$record) {
            return ['ok' => false, 'message' => 'Invalid or expired code. Please request a new one.'];
        }

        if (($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            $this->forget($phone, $record['token'] ?? null);

            return ['ok' => false, 'locked' => true, 'message' => 'Too many attempts. Please request a new code.'];
        }

        if (!Hash::check($otp, $record['hash'])) {
            $record['attempts'] = ($record['attempts'] ?? 0) + 1;
            Cache::put($this->otpKey($digits), $record, now()->addMinutes(self::TTL_MINUTES));

            return ['ok' => false, 'message' => 'Invalid or expired code.'];
        }

        $token = $record['token'] ?? null;
        $pending = $this->pending($token);

        if (!$pending) {
            return ['ok' => false, 'message' => 'This sign-up request has expired. Please start again.'];
        }

        return ['ok' => true, 'pending' => $pending, 'chat' => $this->chat($phone)];
    }

    /**
     * Drop every cache entry belonging to a sign-up attempt.
     */
    public function forget(string $phone, ?string $token = null): void
    {
        $digits = $this->digits($phone);

        $token = $token ?? Cache::get($this->phoneKey($digits));

        Cache::forget($this->otpKey($digits));
        Cache::forget($this->chatKey($digits));
        Cache::forget($this->phoneKey($digits));

        if ($token) {
            Cache::forget($this->pendingKey($token));
            Cache::forget('telegram_link:' . $token);
        }
    }

    public function digits(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?? '';
    }

    protected function pendingKey(string $token): string
    {
        return 'signup_pending:' . $token;
    }

    protected function phoneKey(string $digits): string
    {
        return 'signup_phone:' . $digits;
    }

    protected function otpKey(string $digits): string
    {
        return 'signup_otp:' . $digits;
    }

    protected function chatKey(string $digits): string
    {
        return 'signup_chat:' . $digits;
    }
}
