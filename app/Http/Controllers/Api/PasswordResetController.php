<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordOtpService;
use App\Services\PhoneVerificationService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function __construct(
        protected TelegramService $telegram,
        protected PasswordOtpService $otp,
        protected PhoneVerificationService $phoneVerification,
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
if (!$this->phoneVerification->digits($user->phone ?? '')) {
            return response()->json([
                'method' => 'none',
                'message' => 'This account has no phone number, so we cannot send a verification code.',
            ], 422);
        }

        // Already linked: send the code straight away to the one Telegram we trust.
        if (!empty($user->telegram_chat_id)) {
            $code = $this->otp->generate($user);

            $this->telegram->sendMessage(
                (string) $user->telegram_chat_id,
                "Your SABAY SHOP verification code is {$code}. It expires in " . PasswordOtpService::TTL_MINUTES . ' minutes.'
            );

            return response()->json([
                'method' => 'otp',
                'channel' => 'telegram',
                'reset_token' => $this->otp->newIntent($user),
                'expires_in' => PasswordOtpService::TTL_MINUTES * 60,
                'message' => 'We sent a 6-digit code to your Telegram.',
            ]);
        }

        // No Telegram is linked, so there is nothing we can prove ownership with.
        // Issuing a link here would let anybody holding this phone number link
        // their own Telegram and reset the password.
        return response()->json([
            'method' => 'telegram_not_linked',
            'message' => 'For your security we only send codes to the Telegram already connected to this account. '
                .'Sign in and connect Telegram under Profile, or contact support to recover this account.',
        ], 422);
    }

    /**
     * Complete the reset using the Telegram OTP.
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:255',
            'otp' => 'required|string',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $this->findUser(trim($data['login']));

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        if (!$this->otp->intentMatches($user, $data['reset_token'])) {
            return response()->json([
                'message' => 'This reset request has expired. Please request a new code.',
            ], 422);
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
        $this->otp->forgetIntent($user);

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
