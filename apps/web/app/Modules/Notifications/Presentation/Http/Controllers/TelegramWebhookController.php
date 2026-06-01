<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.telegram.webhook_secret', '');

        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            return response(status: 403);
        }

        $message = $request->array('message');
        $chatId = Arr::get($message, 'chat.id');
        $text = trim((string) Arr::get($message, 'text', ''));

        if (! is_int($chatId) && ! is_string($chatId)) {
            return response(status: 200);
        }

        $token = $this->startToken($text);

        if ($token === null) {
            $this->sendMessage((string) $chatId, 'Откройте подключение Telegram из настроек Montry.');

            return response(status: 200);
        }

        $user = User::query()
            ->where('telegram_connection_token', $token)
            ->first();

        if (! $user) {
            $this->sendMessage((string) $chatId, 'Не удалось подключить Telegram: код устарел или не найден.');

            return response(status: 200);
        }

        $user->forceFill([
            'telegram_notifications_enabled' => true,
            'telegram_chat_id' => (string) $chatId,
            'telegram_username' => $this->username($message),
            'telegram_connected_at' => now(),
        ])->save();

        $this->sendMessage((string) $chatId, 'Telegram подключен к Montry. Уведомления появятся после включения отправки инцидентов.');

        return response(status: 200);
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
     * @param array<string, mixed> $message
     */
    private function username(array $message): ?string
    {
        $username = trim((string) Arr::get($message, 'from.username', ''));

        if ($username !== '') {
            return $username;
        }

        $firstName = trim((string) Arr::get($message, 'from.first_name', ''));
        $lastName = trim((string) Arr::get($message, 'from.last_name', ''));
        $fullName = trim("{$firstName} {$lastName}");

        return $fullName !== '' ? $fullName : null;
    }

    private function sendMessage(string $chatId, string $text): void
    {
        $token = (string) config('services.telegram.bot_token', '');

        if ($token === '') {
            return;
        }

        try {
            Http::asForm()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);
        } catch (ConnectionException) {
            //
        }
    }
}
