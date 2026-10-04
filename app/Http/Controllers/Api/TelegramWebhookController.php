<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramUpdateHandler;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    /**
     * Receives updates from the Telegram Bot API (production delivery mode).
     */
    public function webhook(Request $request, TelegramUpdateHandler $handler)
    {
        $secret = config('services.telegram.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response()->json(['ok' => false], 403);
        }

        $handler->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
