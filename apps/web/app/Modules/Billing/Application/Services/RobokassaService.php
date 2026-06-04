<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use Illuminate\Http\Request;

final class RobokassaService
{
    /**
     * @return array<string, mixed>
     */
    public function paymentForm(Payment $payment, ?string $email = null): array
    {
        $isConfigured = $this->isConfigured();

        if (! $isConfigured) {
            return [
                'is_configured' => false,
                'is_test' => $this->isTest(),
                'allow_test_confirmation' => $this->isTest(),
                'action' => null,
                'method' => 'POST',
                'fields' => [],
                'result_url' => route('billing.robokassa.result'),
                'success_url' => route('billing.robokassa.success'),
                'fail_url' => route('billing.robokassa.fail'),
            ];
        }

        $payment->loadMissing('subscription.plan');

        $fields = [
            'MerchantLogin' => $this->merchantLogin(),
            'OutSum' => $this->formatAmount($payment->amount_cents),
            'InvId' => (string) $payment->id,
            'Description' => $this->description($payment),
            'Culture' => $this->culture(),
            'Encoding' => 'utf-8',
            'Shp_payment_id' => (string) $payment->id,
        ];

        if ($email !== null && trim($email) !== '') {
            $fields['Email'] = trim($email);
        }

        if ($this->isTest()) {
            $fields['IsTest'] = '1';
        }

        $fields['SignatureValue'] = $this->buildStartSignature($fields);

        return [
            'is_configured' => true,
            'is_test' => $this->isTest(),
            'allow_test_confirmation' => $this->isTest(),
            'action' => $this->paymentUrl(),
            'method' => 'POST',
            'fields' => $fields,
            'result_url' => route('billing.robokassa.result'),
            'success_url' => route('billing.robokassa.success'),
            'fail_url' => route('billing.robokassa.fail'),
        ];
    }

    public function isConfigured(): bool
    {
        return $this->merchantLogin() !== ''
            && $this->password1() !== ''
            && $this->password2() !== '';
    }

    public function isTest(): bool
    {
        $mode = strtolower(trim((string) config('services.robokassa.mode', 'test')));

        return in_array($mode, ['test', 'testing', 'sandbox', 'local'], true);
    }

    public function paymentIdFromRequest(Request $request): ?int
    {
        $value = $this->requestValue($request, ['InvId', 'InvID', 'InvoiceID', 'invoiceID']);

        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        $paymentId = (int) $value;

        return $paymentId > 0 ? $paymentId : null;
    }

    public function providerPaymentIdFromRequest(Request $request): ?string
    {
        $value = $this->requestValue($request, ['OpKey', 'opKey', 'PaymentMethod']);

        return $value !== null && $value !== '' ? $value : null;
    }

    public function amountCentsFromRequest(Request $request): ?int
    {
        $value = $this->requestValue($request, ['OutSum', 'outSum', 'IncSum', 'incSum']);

        if ($value === null) {
            return null;
        }

        return $this->moneyToCents($value);
    }

    public function resultSignatureIsValid(Request $request): bool
    {
        return $this->signatureIsValid($request, $this->password2());
    }

    public function successSignatureIsValid(Request $request): bool
    {
        return $this->signatureIsValid($request, $this->password1());
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizedPayload(Request $request): array
    {
        $payload = [];

        foreach ($request->all() as $key => $value) {
            $key = (string) $key;

            if (strtolower($key) === 'signaturevalue') {
                $signature = is_scalar($value) ? (string) $value : '';
                $payload['signature_hash'] = $signature !== '' ? hash('sha256', $signature) : null;
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    public function formatAmount(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }

    private function buildStartSignature(array $fields): string
    {
        $parts = [
            $this->merchantLogin(),
            (string) $fields['OutSum'],
            (string) $fields['InvId'],
            $this->password1(),
        ];

        foreach ($this->shpParams($fields) as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return $this->hash(implode(':', $parts));
    }

    private function signatureIsValid(Request $request, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $outSum = $this->requestValue($request, ['OutSum', 'outSum']);
        $invId = $this->requestValue($request, ['InvId', 'InvID', 'InvoiceID', 'invoiceID']);
        $signature = $this->requestValue($request, ['SignatureValue', 'signatureValue']);

        if ($outSum === null || $invId === null || $signature === null) {
            return false;
        }

        $parts = [$outSum, $invId, $password];

        foreach ($this->shpParams($request->all()) as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return hash_equals(strtolower($this->hash(implode(':', $parts))), strtolower($signature));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function shpParams(array $values): array
    {
        $shp = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (! preg_match('/^Shp_[A-Za-z0-9_]+$/', $key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $shp[$key] = (string) $value;
            }
        }

        ksort($shp, SORT_STRING);

        return $shp;
    }

    /**
     * @param list<string> $keys
     */
    private function requestValue(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);

            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        $lowerKeys = array_map('strtolower', $keys);

        foreach ($request->all() as $key => $value) {
            if (! in_array(strtolower((string) $key), $lowerKeys, true)) {
                continue;
            }

            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return null;
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

    private function hash(string $base): string
    {
        $algorithm = strtolower(trim((string) config('services.robokassa.hash_algorithm', 'md5')));

        if ($algorithm === '' || ! in_array($algorithm, hash_algos(), true)) {
            $algorithm = 'md5';
        }

        return strtoupper(hash($algorithm, $base));
    }

    private function description(Payment $payment): string
    {
        $planName = $payment->subscription?->plan?->name ?? 'тарифа';
        $description = "Оплата {$planName} в Montry, платеж #{$payment->id}";

        return mb_substr($description, 0, 100);
    }

    private function merchantLogin(): string
    {
        return trim((string) config('services.robokassa.merchant_login', ''));
    }

    private function password1(): string
    {
        $key = $this->isTest() ? 'test_password1' : 'password1';
        $password = trim((string) config("services.robokassa.{$key}", ''));

        return $password !== '' ? $password : trim((string) config('services.robokassa.password1', ''));
    }

    private function password2(): string
    {
        $key = $this->isTest() ? 'test_password2' : 'password2';
        $password = trim((string) config("services.robokassa.{$key}", ''));

        return $password !== '' ? $password : trim((string) config('services.robokassa.password2', ''));
    }

    private function paymentUrl(): string
    {
        return trim((string) config('services.robokassa.payment_url', 'https://auth.robokassa.ru/Merchant/Index.aspx'));
    }

    private function culture(): string
    {
        $culture = strtolower(trim((string) config('services.robokassa.culture', 'ru')));

        return in_array($culture, ['ru', 'en'], true) ? $culture : 'ru';
    }
}
