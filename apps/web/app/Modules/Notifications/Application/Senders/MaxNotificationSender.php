<?php

namespace App\Modules\Notifications\Application\Senders;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MaxNotificationSender implements NotificationSenderInterface
{
    public function supports(NotificationChannel $channel): bool
    {
        return $channel->type === 'max';
    }

    public function send(NotificationChannel $channel, NotificationMessage $message): void
    {
        $token = config('services.max.bot_token');
        $chatId = $channel->settings['chat_id'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Max bot token is not configured.');
        }

        if (! is_string($chatId) && ! is_int($chatId)) {
            throw new RuntimeException('Max notification channel has no chat_id.');
        }

        $this->request($token)
            ->post($this->sendMessageUrl(), [
                'recipient' => [
                    'chat_id' => (string) $chatId,
                ],
                'text' => $message->body,
            ])
            ->throw();
    }

    private function request(string $token): PendingRequest
    {
        $request = Http::acceptJson()->timeout(10);
        $authMode = (string) config('services.max.auth_mode', 'query');

        if ($authMode === 'bearer') {
            return $request->withToken($token);
        }

        $tokenParameter = (string) config('services.max.token_query_parameter', 'access_token');

        return $request->withQueryParameters([
            $tokenParameter !== '' ? $tokenParameter : 'access_token' => $token,
        ]);
    }

    private function sendMessageUrl(): string
    {
        $url = trim((string) config('services.max.send_message_url', ''));

        if ($url !== '') {
            return $url;
        }

        return rtrim((string) config('services.max.api_base_url', 'https://botapi.max.ru'), '/') . '/messages';
    }
}