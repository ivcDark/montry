<?php

namespace App\Modules\Notifications\Application\Senders;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TelegramNotificationSender implements NotificationSenderInterface
{
    public function supports(NotificationChannel $channel): bool
    {
        return $channel->type === 'telegram';
    }

    public function send(NotificationChannel $channel, NotificationMessage $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = $channel->settings['chat_id'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        if (! is_string($chatId) && ! is_int($chatId)) {
            throw new RuntimeException('Telegram notification channel has no chat_id.');
        }

        Http::asForm()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => (string) $chatId,
                'text' => $message->body,
            ])
            ->throw();
    }
}
