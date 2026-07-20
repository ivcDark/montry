<?php

namespace App\Modules\Notifications\Infrastructure\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class TelegramHttpClient
{
    public function request(int $timeoutSeconds = 10): PendingRequest
    {
        $request = Http::asForm()->timeout($timeoutSeconds);
        $proxy = trim((string) config('services.telegram.http_proxy', ''));

        if ($proxy !== '') {
            $request = $request->withOptions([
                'proxy' => $proxy,
            ]);
        }

        return $request;
    }

    public function botApiUrl(string $token, string $method): string
    {
        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
