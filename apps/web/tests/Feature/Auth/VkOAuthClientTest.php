<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\DTO\VkUserData;
use App\Modules\Auth\Services\VkOAuthClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VkOAuthClientTest extends TestCase
{
    public function test_authorization_url_contains_vk_oauth_parameters(): void
    {
        config()->set('services.vk.client_id', 'vk-client-id');
        config()->set('services.vk.redirect_uri', 'https://montry.test/auth/vk/callback');
        config()->set('services.vk.scope', 'email');
        config()->set('services.vk.authorize_url', 'https://oauth.vk.com/authorize');
        config()->set('services.vk.api_version', '5.199');

        $url = app(VkOAuthClient::class)->authorizationUrl('state-token');
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://oauth.vk.com/authorize?', $url);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('vk-client-id', $query['client_id']);
        $this->assertSame('https://montry.test/auth/vk/callback', $query['redirect_uri']);
        $this->assertSame('email', $query['scope']);
        $this->assertSame('state-token', $query['state']);
        $this->assertSame('5.199', $query['v']);
    }

    public function test_user_from_code_returns_vk_user_data(): void
    {
        config()->set('services.vk.client_id', 'vk-client-id');
        config()->set('services.vk.client_secret', 'vk-secret');
        config()->set('services.vk.redirect_uri', 'https://montry.test/auth/vk/callback');
        config()->set('services.vk.token_url', 'https://oauth.vk.com/access_token');
        config()->set('services.vk.user_info_url', 'https://api.vk.com/method/users.get');
        config()->set('services.vk.api_version', '5.199');

        Http::fake([
            'oauth.vk.com/access_token*' => Http::response([
                'access_token' => 'vk-access-token',
                'user_id' => 12345,
                'email' => 'USER@EXAMPLE.COM',
            ]),
            'api.vk.com/method/users.get*' => Http::response([
                'response' => [[
                    'id' => 12345,
                    'first_name' => 'Ivan',
                    'last_name' => 'Petrov',
                    'screen_name' => 'ivan',
                ]],
            ]),
        ]);

        $user = app(VkOAuthClient::class)->userFromCode('auth-code');

        $this->assertInstanceOf(VkUserData::class, $user);
        $this->assertSame('12345', $user->id);
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('Ivan Petrov', $user->name);
    }
}
