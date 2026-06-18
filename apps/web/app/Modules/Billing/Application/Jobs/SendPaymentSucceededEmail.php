<?php

namespace App\Modules\Billing\Application\Jobs;

use App\Modules\Billing\Application\Mail\PaymentSucceededMail;
use App\Modules\Billing\Application\Services\BillingAddonCatalog;
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

final class SendPaymentSucceededEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $paymentId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(BillingAddonCatalog $addonCatalog): void
    {
        $payment = Payment::query()
            ->with(['organization.users', 'subscription.plan', 'subscription.items'])
            ->find($this->paymentId);

        if ($payment === null || $payment->status !== 'paid' || $payment->subscription?->plan === null) {
            return;
        }

        $catalog = $addonCatalog->all();
        $items = $payment->subscription->items
            ->map(fn ($item): array => [
                'name' => $catalog[$item->code]['name'] ?? $item->code,
                'quantity' => (int) $item->quantity,
                'amount' => $this->formatMoney((int) $item->quantity * (int) $item->unit_price_cents),
            ])
            ->values()
            ->all();

        foreach ($this->recipients($payment) as $recipient) {
            Mail::to($recipient->email)->send(new PaymentSucceededMail(
                organizationName: $payment->organization->name,
                planName: $payment->subscription->plan->name,
                amount: $this->formatMoney($payment->amount_cents),
                paidAt: ($payment->paid_at ?? now())->timezone(config('app.timezone'))->format('d.m.Y H:i'),
                items: $items,
            ));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Payment $payment): Collection
    {
        $activeUsers = $payment->organization->users
            ->filter(fn (User $user): bool => $user->pivot->status === 'active');
        $owners = $activeUsers
            ->filter(fn (User $user): bool => $user->pivot->role === 'owner')
            ->values();

        return $owners->isNotEmpty() ? $owners : $activeUsers->values();
    }

    private function formatMoney(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, ',', ' ').' ₽';
    }
}
