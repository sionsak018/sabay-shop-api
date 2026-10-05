<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhoneVerificationService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class RegistrationVerificationController extends Controller
{
    public function __construct(
        protected TelegramService $telegram,
        protected PhoneVerificationService $verification,
    ) {
    }

    /**
     * Step 1 of a phone sign-up: park the details and hand back a Telegram link.
     * The account itself is only created once the code is confirmed.
     */
    public function start(Request $request)
    {
        if (!$this->telegram->configured()) {
            return response()->json([
                'message' => 'Phone registration is temporarily unavailable. Please try again later.',
            ], 503);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|min:6|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phone = $this->verification->digits(trim($data['phone']));

        if ($phone === '' || strlen($phone) < 6) {
            return $this->invalidPhone('Enter a valid phone number.');
        }

        if (User::where('phone', $phone)->exists()) {
            return $this->invalidPhone('That phone number is already registered.');
        }

        $pending = $this->verification->begin([
            'name' => trim($data['name']),
            'phone' => $phone,
            'password' => $data['password'],
        ]);

        $this->telegram->rememberLink($pending['token'], ['type' => 'signup']);

        return response()->json([
            'method' => 'telegram_link',
            'link' => $this->telegram->deepLink($pending['token']),
            'verify_token' => $pending['verify_token'],
            'bot_username' => config('services.telegram.bot_username'),
            'phone' => $this->maskPhone($phone),
            'expires_in' => PhoneVerificationService::LINK_TTL_MINUTES * 60,
            'message' => 'Open Telegram, press Start, then enter the code you receive.',
        ]);
    }

    /**
     * Step 2: confirm the Telegram code and create the account.
     */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
            'verify_token' => 'required|string',
        ]);

        $result = $this->verification->attempt($data['phone'], $data['otp'], $data['verify_token']);

        if (!$result['ok']) {
            $status = !empty($result['locked']) ? 429 : 422;

            return response()->json(['message' => $result['message']], $status);
        }

        $pending = $result['pending'];

        if (User::where('phone', $pending['phone'])->exists()) {
            $this->verification->forget($pending['phone']);

            return $this->invalidPhone('That phone number is already registered.');
        }

        $user = User::create([
            'name' => $pending['name'],
            'email' => null,
            'phone' => $pending['phone'],
            'password' => $pending['password'],
        ]);

        $chat = $result['chat'] ?? null;

        if (!empty($chat['chat_id'])) {
            $user->telegram_chat_id = $chat['chat_id'];
            $user->telegram_username = $chat['username'] ?? null;
            $user->save();
        }

        $this->verification->forget($pending['phone']);

        return response()->json([
            'user' => $user->load('roles.permissions'),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ], 201);
    }

    protected function invalidPhone(string $message)
    {
        return response()->json([
            'message' => $message,
            'errors' => ['phone' => [$message]],
        ], 422);
    }

    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($phone, -4);
    }
}
