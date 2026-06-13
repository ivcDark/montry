<?php

namespace App\Modules\Billing\Application\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionPastDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $planName,
        public readonly int $daysPastDue,
        public readonly string $freeSwitchDate,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Необходимо оплатить тариф Montry')
            ->view('emails.billing.subscription-past-due-reminder');
    }
}
