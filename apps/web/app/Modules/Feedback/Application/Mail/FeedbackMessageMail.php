<?php

namespace App\Modules\Feedback\Application\Mail;

use App\Modules\Feedback\Application\Commands\SendFeedbackMessageCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class FeedbackMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SendFeedbackMessageCommand $feedback,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Новое обращение с сайта Montry')
            ->replyTo($this->feedback->email, $this->feedback->name)
            ->view('emails.feedback.message');
    }
}
