<?php

namespace App\Modules\Billing\Application\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PaymentSucceededMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, quantity: int, amount: string}>  $items
     */
    public function __construct(
        public readonly string $organizationName,
        public readonly string $planName,
        public readonly string $amount,
        public readonly string $paidAt,
        public readonly array $items,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Оплата тарифа {$this->planName} прошла успешно")
            ->view('emails.billing.payment-succeeded');
    }
}
