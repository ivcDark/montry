<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Actions\AuthenticateYandexUser;
use App\Modules\Auth\DTO\YandexUserData;
use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticateYandexUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_yandex_dot_com_alias_is_linked_to_existing_yandex_dot_ru_user(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'gold1995vov@yandex.ru',
            'yandex_id' => null,
        ]);

        $authenticatedUser = app(AuthenticateYandexUser::class)->handle(new YandexUserData(
            id: 'yandex-user-123',
            email: 'gold1995vov@yandex.com',
            name: 'Gold Vov',
        ));

        $this->assertSame($existingUser->id, $authenticatedUser->id);
        $this->assertSame('gold1995vov@yandex.ru', $authenticatedUser->email);
        $this->assertSame('yandex-user-123', $authenticatedUser->yandex_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_new_yandex_user_receives_registration_completed_email(): void
    {
        Mail::fake();

        $authenticatedUser = app(AuthenticateYandexUser::class)->handle(new YandexUserData(
            id: 'new-yandex-user-123',
            email: 'new-user@yandex.ru',
            name: 'New Yandex User',
        ));

        Mail::assertSent(RegistrationCompletedMail::class, function (RegistrationCompletedMail $mail) use ($authenticatedUser): bool {
            return $mail->hasTo($authenticatedUser->email);
        });
    }
}
