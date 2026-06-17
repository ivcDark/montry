<?php

namespace Tests\Feature\Feedback;

use App\Modules\Feedback\Application\Mail\FeedbackMessageMail;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class FeedbackMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_send_feedback_message_to_admin(): void
    {
        Mail::fake();
        config()->set('mail.feedback_to.address', 'admin@example.com');
        config()->set('mail.feedback_to.name', 'Montry Admin');

        $this
            ->from('/')
            ->post('/feedback', [
                'name' => 'Иван',
                'email' => 'ivan@example.com',
                'message' => 'Нужен индивидуальный тариф на большое количество сайтов.',
            ])
            ->assertRedirect('/');

        Mail::assertSent(FeedbackMessageMail::class, function (FeedbackMessageMail $mail): bool {
            $mail->build();

            return $mail->hasTo('admin@example.com')
                && $mail->hasReplyTo('ivan@example.com')
                && $mail->feedback->name === 'Иван'
                && $mail->feedback->message === 'Нужен индивидуальный тариф на большое количество сайтов.';
        });
    }

    public function test_feedback_message_requires_valid_fields(): void
    {
        Mail::fake();

        $this
            ->from('/')
            ->post('/feedback', [
                'name' => '',
                'email' => 'not-email',
                'message' => '',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertNothingSent();
    }

    public function test_authenticated_user_can_send_support_request_from_account(): void
    {
        Mail::fake();
        config()->set('mail.feedback_to.address', 'support@example.com');
        config()->set('mail.feedback_to.name', 'Montry Support');

        $user = User::factory()->create([
            'name' => 'Владимир',
            'email' => 'vladimir@example.com',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Студия Север',
            'slug' => 'studio-sever',
            'timezone' => '+3',
            'status' => 'active',
        ]);
        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->from('/sites')
            ->post('/feedback', [
                'name' => 'Владимир',
                'email' => 'vladimir@example.com',
                'subject' => 'Не приходит Telegram-уведомление',
                'message' => 'Проверка упала, но уведомление в Telegram не пришло.',
                'source' => 'account',
            ])
            ->assertRedirect('/sites')
            ->assertSessionHas('success');

        Mail::assertSent(FeedbackMessageMail::class, function (FeedbackMessageMail $mail) use ($user, $organization): bool {
            $mail->build();

            return $mail->hasTo('support@example.com')
                && $mail->hasReplyTo('vladimir@example.com')
                && $mail->subject === 'Обращение из личного кабинета'
                && $mail->feedback->source === 'account'
                && $mail->feedback->subject === 'Не приходит Telegram-уведомление'
                && $mail->feedback->message === 'Проверка упала, но уведомление в Telegram не пришло.'
                && $mail->feedback->userId === $user->id
                && $mail->feedback->organizationId === $organization->id;
        });
    }

    public function test_feedback_email_uses_montry_branded_design(): void
    {
        $mail = new FeedbackMessageMail(new \App\Modules\Feedback\Application\Commands\SendFeedbackMessageCommand(
            name: 'Иван',
            email: 'ivan@example.com',
            message: 'Нужен индивидуальный тариф.',
            pageUrl: 'https://montry.test/#feedback-form',
            ipAddress: '127.0.0.1',
            userAgent: 'Feature Test',
        ));

        $html = $mail->render();

        $this->assertStringContainsString('Montry', $html);
        $this->assertStringContainsString('Иван', $html);
        $this->assertStringContainsString('ivan@example.com', $html);
        $this->assertStringContainsString('Нужен индивидуальный тариф.', $html);
        $this->assertStringContainsString('#24A869', $html);
    }

    public function test_account_feedback_email_contains_user_context(): void
    {
        $mail = new FeedbackMessageMail(new \App\Modules\Feedback\Application\Commands\SendFeedbackMessageCommand(
            name: 'Владимир',
            email: 'vladimir@example.com',
            message: 'Проверка упала, но уведомление в Telegram не пришло.',
            subject: 'Не приходит Telegram-уведомление',
            source: 'account',
            pageUrl: 'https://montry.test/sites',
            ipAddress: '127.0.0.1',
            userAgent: 'Feature Test',
            userId: 42,
            userName: 'Владимир',
            userEmail: 'vladimir@example.com',
            organizationId: 7,
            organizationName: 'Студия Север',
        ));

        $mail->build();
        $html = $mail->render();

        $this->assertSame('Обращение из личного кабинета', $mail->subject);
        $this->assertStringContainsString('Обращение из личного кабинета', $html);
        $this->assertStringContainsString('Не приходит Telegram-уведомление', $html);
        $this->assertStringContainsString('#42', $html);
        $this->assertStringContainsString('Студия Север', $html);
    }
}
