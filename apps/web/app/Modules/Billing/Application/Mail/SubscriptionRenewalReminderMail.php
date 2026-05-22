<?php

namespace App\Modules\Billing\Application\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $currentPlanName,
        public readonly ?string $upcomingPlanName,
        public readonly int $daysUntilExpiration,
        public readonly string $expirationDate,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Тариф Montry заканчивается через {$this->daysUntilExpiration} дн.")
            ->view('emails.billing.subscription-renewal-reminder');
    }
}
