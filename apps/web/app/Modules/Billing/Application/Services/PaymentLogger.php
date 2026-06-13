<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class PaymentLogger
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function info(string $event, ?Payment $payment = null, ?Request $request = null, string $message = '', array $payload = [], array $context = []): void
    {
        $this->record('info', $event, $payment, $request, $message, $payload, $context);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function warning(string $event, ?Payment $payment = null, ?Request $request = null, string $message = '', array $payload = [], array $context = []): void
    {
        $this->record('warning', $event, $payment, $request, $message, $payload, $context);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function error(string $event, ?Payment $payment = null, ?Request $request = null, string $message = '', array $payload = [], array $context = [], ?Throwable $exception = null): void
    {
        $this->record('error', $event, $payment, $request, $message, $payload, $context, $exception);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    private function record(
        string $level,
        string $event,
        ?Payment $payment,
        ?Request $request,
        string $message,
        array $payload,
        array $context,
        ?Throwable $exception = null,
    ): void {
        try {
            PaymentLog::query()->create([
                'payment_id' => $payment?->id,
                'organization_id' => $payment?->organization_id,
                'provider' => $payment?->provider ?: 'robokassa',
                'level' => $level,
                'event' => $this->normalizeEvent($event),
                'message' => $message !== '' ? Str::limit($message, 2000, '') : null,
                'request_method' => $request?->method(),
                'request_path' => $request?->path(),
                'ip_hash' => $this->hashNullable($request?->ip()),
                'payload' => $this->sanitizeArray($payload),
                'context' => $this->sanitizeArray($context),
                'exception_class' => $exception !== null ? $exception::class : null,
                'exception_message' => $exception ? Str::limit($exception->getMessage(), 2000, '') : null,
            ]);
        } catch (Throwable $logException) {
            Log::warning('payment log write failed', [
                'event' => $event,
                'level' => $level,
                'payment_id' => $payment?->id,
                'exception' => $logException::class,
            ]);
        }
    }

    private function normalizeEvent(string $event): string
    {
        $event = trim($event);

        if ($event === '' || ! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $event)) {
            return 'payment.log';
        }

        return $event;
    }

    private function hashNullable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values): array
    {
        $values = Arr::except($values, [
            'SignatureValue',
            'signature',
            'password',
            'password1',
            'password2',
            'secret',
            'token',
            'authorization',
        ]);

        $sanitized = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $lowerKey = strtolower($key);

            if (str_contains($lowerKey, 'signature')
                || str_contains($lowerKey, 'password')
                || str_contains($lowerKey, 'secret')
                || str_contains($lowerKey, 'token')
                || str_contains($lowerKey, 'authorization')) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
