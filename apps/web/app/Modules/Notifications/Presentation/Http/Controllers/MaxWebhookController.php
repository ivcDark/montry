<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notifications\Application\Services\SyncMaxNotificationChannels;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MaxWebhookController extends Controller
{
    public function __invoke(Request $request, SyncMaxNotificationChannels $syncMaxChannels): Response
    {
        $secret = (string) config('services.max.webhook_secret', '');
        $incomingSecret = (string) ($request->header('X-Max-Bot-Api-Secret-Token') ?: $request->header('X-Max-Bot-Api-Secret'));

        if ($secret !== '' && ! hash_equals($secret, $incomingSecret)) {
            return response(status: 403);
        }

        $payload = $request->all();
        $chatId = $this->chatId($payload);

        if ($chatId === null) {
            return response(status: 200);
        }

        $token = $this->connectionToken($payload);

        if ($token === null) {
            $this->sendMessage($chatId, 'Откройте подключение Max из настроек Montri.');

            return response(status: 200);
        }

        $user = User::query()
            ->where('max_connection_token', $token)
            ->first();

        if (! $user) {
            $this->sendMessage($chatId, 'Не удалось подключить Max: код устарел или не найден.');

            return response(status: 200);
        }

        $user->forceFill([
            'max_notifications_enabled' => true,
            'max_chat_id' => $chatId,
            'max_username' => $this->username($payload),
            'max_connected_at' => now(),
        ])->save();

        $syncMaxChannels->handle($user->refresh());

        $this->sendMessage($chatId, 'Max подключен к Montri. Теперь вы будете получать уведомления об инцидентах.');

        return response(status: 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function chatId(array $payload): ?string
    {
        $chatId = Arr::get($payload, 'message.chat.id')
            ?? Arr::get($payload, 'message.recipient.chat_id')
            ?? Arr::get($payload, 'recipient.chat_id')
            ?? Arr::get($payload, 'chat_id');

        if (! is_int($chatId) && ! is_string($chatId)) {
            return null;
        }

        $chatId = trim((string) $chatId);

        return $chatId !== '' ? $chatId : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function connectionToken(array $payload): ?string
    {
        $payloadToken = Arr::get($payload, 'payload')
            ?? Arr::get($payload, 'start_payload')
            ?? Arr::get($payload, 'message.payload')
            ?? Arr::get($payload, 'message.body.payload');

        if (is_string($payloadToken) && trim($payloadToken) !== '') {
            return trim($payloadToken);
        }

        return $this->startToken($this->messageText($payload));
    }

    private function startToken(string $text): ?string
    {
        if (! Str::startsWith($text, '/start')) {
            return null;
        }

        $parts = preg_split('/\s+/', $text, 2);
        $token = trim((string) ($parts[1] ?? ''));

        return $token !== '' ? $token : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function messageText(array $payload): string
    {
        return trim((string) (Arr::get($payload, 'message.text')
            ?? Arr::get($payload, 'message.body.text')
            ?? Arr::get($payload, 'text')
            ?? Arr::get($payload, 'body.text')
            ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function username(array $payload): ?string
    {
        $username = trim((string) (Arr::get($payload, 'message.sender.username')
            ?? Arr::get($payload, 'sender.username')
            ?? Arr::get($payload, 'user.username')
            ?? ''));

        if ($username !== '') {
            return $username;
        }

        $firstName = trim((string) (Arr::get($payload, 'message.sender.first_name')
            ?? Arr::get($payload, 'sender.first_name')
            ?? Arr::get($payload, 'user.first_name')
            ?? ''));
        $lastName = trim((string) (Arr::get($payload, 'message.sender.last_name')
            ?? Arr::get($payload, 'sender.last_name')
            ?? Arr::get($payload, 'user.last_name')
            ?? ''));
        $fullName = trim("{$firstName} {$lastName}");

        return $fullName !== '' ? $fullName : null;
    }

    private function sendMessage(string $chatId, string $text): void
    {
        $token = (string) config('services.max.bot_token', '');

        if ($token === '') {
            return;
        }

        try {
            $request = Http::acceptJson()->timeout(5);
            $authMode = (string) config('services.max.auth_mode', 'query');

            if ($authMode === 'bearer') {
                $request = $request->withToken($token);
            } else {
                $tokenParameter = (string) config('services.max.token_query_parameter', 'access_token');
                $request = $request->withQueryParameters([
                    $tokenParameter !== '' ? $tokenParameter : 'access_token' => $token,
                ]);
            }

            $request->post($this->sendMessageUrl(), [
                'recipient' => [
                    'chat_id' => $chatId,
                ],
                'text' => $text,
            ]);
        } catch (ConnectionException) {
            //
        }
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