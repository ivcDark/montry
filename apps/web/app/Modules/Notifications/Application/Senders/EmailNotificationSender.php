<?php

namespace App\Modules\Notifications\Application\Senders;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Mail\IncidentOpenedMail;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class EmailNotificationSender implements NotificationSenderInterface
{
    public function supports(NotificationChannel $channel): bool
    {
        return $channel->type === 'email';
    }

    public function send(NotificationChannel $channel, NotificationMessage $message): void
    {
        $email = $channel->settings['email'] ?? $channel->user?->email;

        if (! is_string($email) || $email === '') {
            throw new RuntimeException('Email notification channel has no recipient email.');
        }

        if ($message->eventType === 'incident.opened') {
            Mail::to($email)->send(new IncidentOpenedMail($message));

            return;
        }

        Mail::raw($message->body, function ($mail) use ($email, $message): void {
            $mail->to($email)->subject($message->subject);
        });
    }
}
