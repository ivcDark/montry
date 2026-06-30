<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTO\VkUserData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VkOAuthClient
{
    public function authorizationUrl(string $state): string
    {
        $clientId = $this->clientId();

        if ($clientId === '') {
            throw ValidationException::withMessages([
                'vk' => 'Авторизация через VK не настроена.',
            ]);
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => $this->scope(),
            'state' => $state,
            'display' => 'page',
            'v' => $this->apiVersion(),
        ];

        return $this->authorizeUrl() . '?' . http_build_query($params);
    }

    public function userFromCode(string $code): VkUserData
    {
        $tokenPayload = $this->exchangeCodeForToken($code);

        $id = (string) Arr::get($tokenPayload, 'user_id', '');
        $email = Str::lower((string) Arr::get($tokenPayload, 'email', ''));
        $accessToken = (string) Arr::get($tokenPayload, 'access_token', '');

        if ($id === '' || $email === '' || $accessToken === '') {
            throw ValidationException::withMessages([
                'vk' => 'VK не передал email аккаунта. Разрешите доступ к email и попробуйте снова.',
            ]);
        }

        return new VkUserData(
            id: $id,
            email: $email,
            name: $this->resolveName($id, $email, $accessToken),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCodeForToken(string $code): array
    {
        $response = Http::get($this->tokenUrl(), [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'vk' => 'Не удалось подтвердить авторизацию через VK.',
            ]);
        }

        $payload = $response->json();

        if (! is_array($payload) || isset($payload['error'])) {
            throw ValidationException::withMessages([
                'vk' => 'VK вернул некорректный ответ авторизации.',
            ]);
        }

        return $payload;
    }

    private function resolveName(string $id, string $email, string $accessToken): string
    {
        $response = Http::get($this->userInfoUrl(), [
            'user_ids' => $id,
            'fields' => 'screen_name',
            'access_token' => $accessToken,
            'v' => $this->apiVersion(),
        ]);

        if (! $response->successful()) {
            return Str::before($email, '@') ?: 'Пользователь VK';
        }

        $payload = $response->json();

        if (! is_array($payload) || isset($payload['error'])) {
            return Str::before($email, '@') ?: 'Пользователь VK';
        }

        $profile = Arr::first((array) Arr::get($payload, 'response', []));

        if (! is_array($profile)) {
            return Str::before($email, '@') ?: 'Пользователь VK';
        }

        $name = trim(implode(' ', array_filter([
            trim((string) Arr::get($profile, 'first_name', '')),
            trim((string) Arr::get($profile, 'last_name', '')),
        ])));

        if ($name !== '') {
            return $name;
        }

        $screenName = trim((string) Arr::get($profile, 'screen_name', ''));

        return $screenName !== '' ? $screenName : (Str::before($email, '@') ?: 'Пользователь VK');
    }

    private function clientId(): string
    {
        return (string) config('services.vk.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('services.vk.client_secret', '');
    }

    private function redirectUri(): string
    {
        return (string) config('services.vk.redirect_uri');
    }

    private function scope(): string
    {
        return (string) config('services.vk.scope', 'email');
    }

    private function authorizeUrl(): string
    {
        return (string) config('services.vk.authorize_url', 'https://oauth.vk.com/authorize');
    }

    private function tokenUrl(): string
    {
        return (string) config('services.vk.token_url', 'https://oauth.vk.com/access_token');
    }

    private function userInfoUrl(): string
    {
        return (string) config('services.vk.user_info_url', 'https://api.vk.com/method/users.get');
    }

    private function apiVersion(): string
    {
        return (string) config('services.vk.api_version', '5.199');
    }
}
