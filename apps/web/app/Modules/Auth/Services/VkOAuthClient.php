<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTO\VkUserData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VkOAuthClient
{
    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $clientId = $this->clientId();

        if ($clientId === '') {
            throw ValidationException::withMessages([
                'vk' => 'Авторизация через VK не настроена.',
            ]);
        }

        return $this->authorizeUrl() . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => $this->scope(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function userFromCode(string $code, string $codeVerifier, ?string $deviceId): VkUserData
    {
        $tokenPayload = $this->exchangeCodeForToken($code, $codeVerifier, $deviceId);
        $accessToken = (string) Arr::get($tokenPayload, 'access_token', '');

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'vk' => 'VK не вернул токен доступа.',
            ]);
        }

        $profile = $this->fetchUserInfo($accessToken);
        $id = (string) (Arr::get($tokenPayload, 'user_id') ?: Arr::get($profile, 'user_id') ?: Arr::get($profile, 'id', ''));
        $email = Str::lower((string) (Arr::get($tokenPayload, 'email') ?: Arr::get($profile, 'email', '')));

        if ($id === '' || $email === '') {
            throw ValidationException::withMessages([
                'vk' => 'VK не передал email аккаунта. Разрешите доступ к email и попробуйте снова.',
            ]);
        }

        return new VkUserData(
            id: $id,
            email: $email,
            name: $this->resolveName($profile, $email),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCodeForToken(string $code, string $codeVerifier, ?string $deviceId): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];

        if ($this->clientSecret() !== '') {
            $params['client_secret'] = $this->clientSecret();
        }

        if ($deviceId !== null && $deviceId !== '') {
            $params['device_id'] = $deviceId;
        }

        $response = Http::asForm()->post($this->tokenUrl(), $params);

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

    /**
     * @return array<string, mixed>
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $response = Http::asForm()->post($this->userInfoUrl(), [
            'client_id' => $this->clientId(),
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();

        if (! is_array($payload) || isset($payload['error'])) {
            return [];
        }

        $user = Arr::get($payload, 'user');

        if (is_array($user)) {
            return $user;
        }

        $profile = Arr::first((array) Arr::get($payload, 'response', []));

        return is_array($profile) ? $profile : [];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function resolveName(array $profile, string $email): string
    {
        $name = trim((string) Arr::get($profile, 'name', ''));

        if ($name !== '') {
            return $name;
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
        return (string) config('services.vk.authorize_url', 'https://id.vk.com/authorize');
    }

    private function tokenUrl(): string
    {
        return (string) config('services.vk.token_url', 'https://id.vk.com/oauth2/auth');
    }

    private function userInfoUrl(): string
    {
        return (string) config('services.vk.user_info_url', 'https://id.vk.com/oauth2/user_info');
    }
}
