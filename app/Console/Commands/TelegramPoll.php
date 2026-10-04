<?php

namespace App\Console\Commands;

use App\Services\TelegramUpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--once : Process any pending updates then exit}';

    protected $description = 'Long-poll Telegram for updates (local dev alternative to a public webhook).';

    public function handle(TelegramUpdateHandler $handler): int
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        // getUpdates and a registered webhook are mutually exclusive.
        Http::timeout(15)->post("https://api.telegram.org/bot{$token}/deleteWebhook");

        $offset = 0;
        $this->info('Listening for Telegram updates. Press Ctrl+C to stop.');

        while (true) {
            try {
                $response = Http::timeout(40)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 25,
                    'allowed_updates' => ['message'],
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // A dropped long-poll is normal on flaky networks; the bot keeps
                // queueing updates, so just reconnect instead of crashing.
                $this->warn('getUpdates connection lost, retrying: ' . $e->getMessage());

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(3);

                continue;
            }

            if ($response->failed()) {
                $this->error('getUpdates failed: ' . $response->body());

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(3);

                continue;
            }

            foreach ($response->json('result', []) as $update) {
                $offset = ($update['update_id'] ?? 0) + 1;

                try {
                    $handler->handle($update);
                    $this->line('Handled update ' . ($update['update_id'] ?? 0));
                } catch (\Throwable $e) {
                    $this->error('Handler error: ' . $e->getMessage());
                }
            }

            if ($this->option('once')) {
                // Telegram only forgets an update once it is asked again with an
                // offset past it, so without this confirmation the same updates
                // are replayed on the next run.
                if ($offset > 0) {
                    Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                        'offset' => $offset,
                    ]);
                }

                break;
            }
        }

        return self::SUCCESS;
    }
}
