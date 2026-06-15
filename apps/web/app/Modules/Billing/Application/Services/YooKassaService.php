<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class YooKassaService
{
    /**
     * @return array<string, mixed>
     */
    public function paymentPayload(Payment $payment): array
    {
        return [
            'is_configured' => $this->isConfigured(),
            'is_test' => $this->isTest(),
            'checkout_url' => route('billing.payments.yookassa.checkout', $payment),
            'webhook_url' => route('billing.yookassa.webhook'),
        ];
    }

    public function isConfigured(): bool
    {
        return $this->shopId() !== '' && $this->secretKey() !== '';
    }

    public function isTest(): bool
    {
        $mode = strtolower(trim((string) config('services.yookassa.mode', 'test')));

        return in_array($mode, ['test', 'testing', 'sandbox', 'local'], true);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createPayment(Payment $payment, ?string $email = null): array
    {
        $payment->loadMissing('subscription.plan');

        $payload = [
            'amount' => [
                'value' => $this->formatAmount($payment->amount_cents),
                'currency' => $payment->currency,
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => route('billing.yookassa.return', ['payment' => $payment->id]),
            ],
            'description' => $this->description($payment),
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'organization_id' => (string) $payment->organization_id,
                'subscription_id' => (string) $payment->subscription_id,
                'mode' => $this->isTest() ? 'test' : 'prod',
            ],
        ];

        if ($email !== null && trim($email) !== '') {
            $payload['receipt'] = [
                'customer' => ['email' => trim($email)],
                'items' => [[
                    'description' => $this->description($payment),
                    'quantity' => '1.00',
                    'amount' => [
                        'value' => $this->formatAmount($payment->amount_cents),
                        'currency' => $payment->currency,
                    ],
                    'vat_code' => 1,
                    'payment_mode' => 'full_payment',
                    'payment_subject' => 'service',
                ]],
            ];
        }

        return Http::withBasicAuth($this->shopId(), $this->secretKey())
            ->withHeaders([
                'Idempotence-Key' => $this->idempotenceKey($payment),
            ])
            ->acceptJson()
            ->asJson()
            ->post($this->apiUrl().'/payments', $payload)
            ->throw()
            ->json();
    }

    public function webhookIsValid(Request $request): bool
    {
        $secret = trim((string) config('services.yookassa.webhook_secret', ''));

        if ($secret === '') {
            return true;
        }

        $authorization = (string) $request->headers->get('Authorization', '');

        return hash_equals('Bearer '.$secret, $authorization);
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizedPayload(Request $request): array
    {
        $payload = $request->json()->all();

        return is_array($payload) ? $payload : [];
    }

    public function paymentIdFromWebhook(array $payload): ?int
    {
        $value = data_get($payload, 'object.metadata.payment_id');

        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return null;
        }

        $paymentId = (int) $value;

        return $paymentId > 0 ? $paymentId : null;
    }

    public function amountCentsFromWebhook(array $payload): ?int
    {
        $value = data_get($payload, 'object.amount.value');

        if (! is_scalar($value)) {
            return null;
        }

        return $this->moneyToCents((string) $value);
    }

    public function providerPaymentIdFromWebhook(array $payload): ?string
    {
        $value = data_get($payload, 'object.id');

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public function eventStatus(array $payload): ?string
    {
        $event = data_get($payload, 'event');

        return is_scalar($event) ? trim((string) $event) : null;
    }

    public function confirmationUrl(array $response): ?string
    {
        $url = data_get($response, 'confirmation.confirmation_url');

        return is_scalar($url) && filter_var((string) $url, FILTER_VALIDATE_URL) ? (string) $url : null;
    }

    public function formatAmount(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }

    private function moneyToCents(string $value): ?int
    {
        $normalized = str_replace(',', '.', trim($value));

        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $normalized)) {
            return null;
        }

        [$rubles, $kopecks] = array_pad(explode('.', $normalized, 2), 2, '');
        $kopecks = str_pad(substr($kopecks, 0, 2), 2, '0');

        return ((int) $rubles * 100) + (int) $kopecks;
    }

    private function description(Payment $payment): string
    {
        $planName = $payment->subscription?->plan?->name ?? 'тарифа';
        $description = "Оплата {$planName} в Montry, платеж #{$payment->id}";

        return mb_substr($description, 0, 128);
    }

    private function idempotenceKey(Payment $payment): string
    {
        $existing = data_get($payment->payload, 'yookassa_idempotence_key');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $key = (string) Str::uuid();
        $payload = is_array($payment->payload) ? $payment->payload : [];
        $payload['yookassa_idempotence_key'] = $key;
        $payment->forceFill(['payload' => $payload])->save();

        return $key;
    }

    private function shopId(): string
    {
        return trim((string) config('services.yookassa.shop_id', ''));
    }

    private function secretKey(): string
    {
        return trim((string) config('services.yookassa.secret_key', ''));
    }

    private function apiUrl(): string
    {
        return rtrim(trim((string) config('services.yookassa.api_url', 'https://api.yookassa.ru/v3')), '/');
    }
}
