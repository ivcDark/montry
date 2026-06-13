<?php

namespace Tests\Feature\Feedback;

use App\Modules\Feedback\Application\Mail\FeedbackMessageMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class FeedbackMessageTest extends TestCase
{
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
        $this->assertStringContainsString('#0F6BFF', $html);
    }
}
