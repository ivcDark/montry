<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Actions\AuthenticateVkUser;
use App\Modules\Auth\DTO\VkUserData;
use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticateVkUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_email_user_is_linked_to_vk_account(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'user@example.com',
            'vk_id' => null,
        ]);

        $authenticatedUser = app(AuthenticateVkUser::class)->handle(new VkUserData(
            id: '123456',
            email: 'user@example.com',
            name: 'VK User',
        ));

        $this->assertSame($existingUser->id, $authenticatedUser->id);
        $this->assertSame('123456', $authenticatedUser->vk_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_new_vk_user_receives_registration_completed_email(): void
    {
        Mail::fake();

        $authenticatedUser = app(AuthenticateVkUser::class)->handle(new VkUserData(
            id: '654321',
            email: 'new-vk-user@example.com',
            name: 'New VK User',
        ));

        Mail::assertSent(RegistrationCompletedMail::class, function (RegistrationCompletedMail $mail) use ($authenticatedUser): bool {
            return $mail->hasTo($authenticatedUser->email);
        });
    }
}
