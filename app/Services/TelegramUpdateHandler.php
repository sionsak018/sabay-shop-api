<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class TelegramUpdateHandler
{
    public function __construct(
        protected TelegramService $telegram,
        protected PhoneVerificationService $signup,
        protected PasswordOtpService $otp,
    ) {
    }

    /**
     * Handle a single Telegram update. Shared by the webhook controller and the
     * `telegram:poll` command so both delivery modes behave identically.
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        if (!is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;

        if ($chatId === null) {
            return;
        }

        $chatId = (string) $chatId;
        $text = trim((string) ($message['text'] ?? ''));

        if (!str_starts_with($text, '/start')) {
            $this->telegram->sendMessage(
                $chatId,
                'Use the buttons inside SABAY SHOP to sign up or reset your password.'
            );

            return;
        }

        $token = trim(Str::after($text, '/start'));
        $record = $token !== '' ? $this->telegram->pullLink($token) : null;

        if (!$record) {
            $this->telegram->sendMessage(
                $chatId,
                'This link is invalid or has expired. Please request a new one from SABAY SHOP.'
            );

            return;
        }

        match ($record['type'] ?? '') {
            'signup' => $this->handleSignup($token, $chatId, $message),
            'recovery' => $this->handleRecovery($chatId, $message, (int) ($record['user_id'] ?? 0)),
            'link' => $this->handleLink($chatId, $message, (int) ($record['user_id'] ?? 0)),
            default => $this->telegram->sendMessage($chatId, 'This link is no longer valid.'),
        };
    }

    protected function handleSignup(string $token, string $chatId, array $message): void
    {
        $pending = $this->signup->pending($token);

        if (!$pending) {
            $this->telegram->sendMessage($chatId, 'This sign-up request has expired. Please register again.');

            return;
        }

        $code = $this->signup->issueCode(
            $pending['phone'],
            $token,
            $chatId,
            $message['from']['username'] ?? null
        );

        $this->telegram->sendMessage(
            $chatId,
            "Welcome to SABAY SHOP! Your verification code is {$code}. It expires in 10 minutes."
        );
    }

    protected function handleRecovery(string $chatId, array $message, int $userId): void
    {
        $user = User::find($userId);

        if (!$user) {
            $this->telegram->sendMessage($chatId, 'This link is no longer valid. Please request a new one.');

            return;
        }

        if (empty($user->telegram_chat_id)) {
            $this->linkChat($user, $chatId, $message);
        }

        $code = $this->otp->generate($user);

        $this->telegram->sendMessage(
            $chatId,
            "Your SABAY SHOP verification code is {$code}. It expires in 10 minutes."
        );
    }

    protected function handleLink(string $chatId, array $message, int $userId): void
    {
        $user = User::find($userId);

        if (!$user) {
            $this->telegram->sendMessage($chatId, 'This link is no longer valid. Please try again.');

            return;
        }

        $this->linkChat($user, $chatId, $message);

        $this->telegram->sendMessage(
            $chatId,
            'Connected! SABAY SHOP will send your verification codes here.'
        );
    }

    protected function linkChat(User $user, string $chatId, array $message): void
    {
        $user->telegram_chat_id = $chatId;
        $user->telegram_username = $message['from']['username'] ?? null;
        $user->save();
    }
}
