<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordOtpService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function __construct(
        protected TelegramService $telegram,
        protected PasswordOtpService $otp,
    ) {
    }

    /**
     * Start a password reset.
     *  - Google accounts verify with Google.
     *  - Everything else verifies with a Telegram code, which is free.
     */
    public function forgot(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:255',
        ]);

        $user = $this->findUser(trim($data['login']));

        if (!$user) {
            return response()->json([
                'method' => 'none',
                'message' => 'No account found for that phone number or email.',
            ], 404);
        }

        if (!empty($user->google_id)) {
            return response()->json([
                'method' => 'google',
                'message' => 'This account uses Google sign-in. Continue with Google to verify.',
            ]);
        }

        if (!$this->telegram->configured()) {
            return response()->json([
                'method' => 'none',
                'message' => 'Password reset is temporarily unavailable. Please try again later.',
            ], 503);
        }

        // Already linked: send the code straight away.
        if (!empty($user->telegram_chat_id)) {
            $code = $this->otp->generate($user);

            $this->telegram->sendMessage(
                (string) $user->telegram_chat_id,
                "Your SABAY SHOP verification code is {$code}. It expires in 10 minutes."
            );

            return response()->json([
                'method' => 'otp',
                'channel' => 'telegram',
                'expires_in' => PasswordOtpService::TTL_MINUTES * 60,
                'message' => 'We sent a 6-digit code to your Telegram.',
            ]);
        }

        $token = $this->otp->newToken();
        $this->telegram->rememberLink($token, ['type' => 'recovery', 'user_id' => $user->id]);

        return response()->json([
            'method' => 'telegram_link',
            'link' => $this->telegram->deepLink($token),
            'bot_username' => config('services.telegram.bot_username'),
            'expires_in' => 900,
            'message' => 'Open Telegram, press Start, and we will send your code instantly.',
        ]);
    }

    /**
     * Complete the reset using the Telegram OTP.
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:255',
            'otp' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $this->findUser(trim($data['login']));

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $record = $this->otp->record($user);

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        if (($record['attempts'] ?? 0) >= PasswordOtpService::MAX_ATTEMPTS) {
            $this->otp->forget($user);

            return response()->json(['message' => 'Too many attempts. Please request a new code.'], 429);
        }

        if (!Hash::check($data['otp'], $record['hash'])) {
            $this->otp->markFailed($user, $record);

            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        $this->otp->forget($user);

        // Force a re-login everywhere on the old credentials.
        $user->tokens()->delete();

        return response()->json(['message' => 'Password has been reset. You can now sign in.']);
    }

    protected function findUser(string $login): ?User
    {
        $digits = preg_replace('/\D/', '', $login);

        return User::where('email', $login)
            ->orWhere('phone', $login)
            ->orWhere('phone', $digits)
            ->first();
    }
}
