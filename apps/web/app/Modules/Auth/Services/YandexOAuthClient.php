<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTO\YandexUserData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class YandexOAuthClient
{
    public function authorizationUrl(string $state): string
    {
        $clientId = $this->clientId();

        if ($clientId === '') {
            throw ValidationException::withMessages([
                'yandex' => 'Авторизация через Яндекс не настроена.',
            ]);
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ];

        $scope = $this->scope();

        if ($scope !== '') {
            $params['scope'] = $scope;
        }

        return $this->authorizeUrl() . '?' . http_build_query($params);
    }

    public function userFromCode(string $code): YandexUserData
    {
        $token = $this->exchangeCodeForToken($code);

        $response = Http::withHeaders([
            'Authorization' => "OAuth {$token}",
        ])->get($this->userInfoUrl(), [
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'yandex' => 'Не удалось получить данные аккаунта Яндекса.',
            ]);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'yandex' => 'Яндекс вернул некорректный ответ.',
            ]);
        }

        $id = (string) Arr::get($payload, 'id', '');
        $email = Str::lower((string) Arr::get($payload, 'default_email', ''));
        $name = $this->resolveName($payload, $email);

        if ($id === '' || $email === '') {
            throw ValidationException::withMessages([
                'yandex' => 'Яндекс не передал email аккаунта. Разрешите доступ к email и попробуйте снова.',
            ]);
        }

        return new YandexUserData(
            id: $id,
            email: $email,
            name: $name,
        );
    }

    private function exchangeCodeForToken(string $code): string
    {
        $response = Http::asForm()->post($this->tokenUrl(), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'yandex' => 'Не удалось подтвердить авторизацию через Яндекс.',
            ]);
        }

        $accessToken = (string) $response->json('access_token', '');

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'yandex' => 'Яндекс не вернул токен доступа.',
            ]);
        }

        return $accessToken;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveName(array $payload, string $email): string
    {
        foreach (['real_name', 'display_name', 'login'] as $key) {
            $value = trim((string) Arr::get($payload, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return Str::before($email, '@') ?: 'Пользователь Яндекса';
    }

    private function clientId(): string
    {
        return (string) config('services.yandex.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('services.yandex.client_secret', '');
    }

    private function redirectUri(): string
    {
        return (string) config('services.yandex.redirect_uri');
    }

    private function scope(): string
    {
        return (string) config('services.yandex.scope', 'login:info,login:email');
    }

    private function authorizeUrl(): string
    {
        return (string) config('services.yandex.authorize_url', 'https://oauth.yandex.com/authorize');
    }

    private function tokenUrl(): string
    {
        return (string) config('services.yandex.token_url', 'https://oauth.yandex.com/token');
    }

    private function userInfoUrl(): string
    {
        return (string) config('services.yandex.user_info_url', 'https://login.yandex.ru/info');
    }
}
