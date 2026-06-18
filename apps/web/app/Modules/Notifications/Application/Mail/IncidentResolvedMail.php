<?php

namespace App\Modules\Notifications\Application\Mail;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class IncidentResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotificationMessage $notification,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->notification->subject)
            ->view('emails.notifications.incident-resolved');
    }
}
