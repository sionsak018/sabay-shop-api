<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token'))
            && filled(config('services.telegram.bot_username'));
    }

    /**
     * Deep link that opens the bot and hands it a one-time start parameter.
     */
    public function deepLink(string $startParam): ?string
    {
        $username = config('services.telegram.bot_username');

        if (!$username) {
            return null;
        }

        return 'https://t.me/' . ltrim($username, '@') . '?start=' . urlencode($startParam);
    }

    /**
     * Send a chat message through the Bot API. Returns false on any failure so
     * callers never break because Telegram is unreachable.
     */
    public function sendMessage(string $chatId, string $text): bool
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            Log::warning('Telegram bot token is not configured.');

            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            if ($response->failed()) {
                Log::warning('Telegram sendMessage failed.', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage threw an exception.', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Remember what a deep-link token is allowed to do, for LINK_TTL minutes.
     */
    public function rememberLink(string $token, array $record, int $minutes = 15): void
    {
        cache()->put('telegram_link:' . $token, $record, now()->addMinutes($minutes));
    }

    /**
     * Consume a deep-link token exactly once.
     */
    public function pullLink(string $token): ?array
    {
        return cache()->pull('telegram_link:' . $token);
    }
}
