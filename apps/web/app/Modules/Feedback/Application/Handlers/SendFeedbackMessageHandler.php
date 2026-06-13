<?php

namespace App\Modules\Feedback\Application\Handlers;

use App\Modules\Feedback\Application\Commands\SendFeedbackMessageCommand;
use App\Modules\Feedback\Application\Mail\FeedbackMessageMail;
use Illuminate\Support\Facades\Mail;

final readonly class SendFeedbackMessageHandler
{
    public function handle(SendFeedbackMessageCommand $command): void
    {
        Mail::to(
            (string) config('mail.feedback_to.address'),
            (string) config('mail.feedback_to.name'),
        )->send(new FeedbackMessageMail($command));
    }
}
