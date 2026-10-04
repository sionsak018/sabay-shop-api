<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url? : Public webhook URL; defaults to APP_URL/api/telegram/webhook}';

    protected $description = 'Register the Telegram bot webhook and print its status.';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/') . '/api/telegram/webhook';
        $secret = config('services.telegram.webhook_secret');

        $payload = ['url' => $url, 'allowed_updates' => ['message']];
        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        if ($response->failed()) {
            $this->error('setWebhook failed: ' . $response->body());

            return self::FAILURE;
        }

        $this->info("Webhook registered: {$url}");

        $info = Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
        $this->line(json_encode($info->json(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
