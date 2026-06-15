<?php

namespace App\Modules\Billing\Application\Services;

final class PaymentProviderManager
{
    public function current(): string
    {
        $provider = strtolower(trim((string) config('services.payments.provider', 'robokassa')));

        return in_array($provider, ['robokassa', 'yookassa'], true) ? $provider : 'robokassa';
    }

    public function is(string $provider): bool
    {
        return $this->current() === strtolower(trim($provider));
    }
}
