<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __construct(protected TelegramService $telegram)
    {
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'configured' => $this->telegram->configured(),
            'linked' => !empty($user->telegram_chat_id),
            'username' => $user->telegram_username,
        ]);
    }

    /**
     * Generate a one-time deep link that links the signed-in user's Telegram.
     */
    public function link(Request $request)
    {
        $user = $request->user();

        if (!$this->telegram->configured()) {
            return response()->json(['configured' => false, 'linked' => false, 'link' => null]);
        }

        $token = bin2hex(random_bytes(20));
        $this->telegram->rememberLink($token, ['type' => 'link', 'user_id' => $user->id]);

        return response()->json([
            'configured' => true,
            'linked' => !empty($user->telegram_chat_id),
            'username' => $user->telegram_username,
            'link' => $this->telegram->deepLink($token),
            'bot_username' => config('services.telegram.bot_username'),
        ]);
    }

    public function unlink(Request $request)
    {
        $user = $request->user();
        $user->telegram_chat_id = null;
        $user->telegram_username = null;
        $user->save();

        return response()->json(['linked' => false]);
    }
}
