<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\CheckoutService;
use App\Modules\Billing\Application\Services\PaymentLogger;
use App\Modules\Billing\Application\Services\YooKassaService;
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class YooKassaController extends Controller
{
    public function checkout(
        Request $request,
        Payment $payment,
        GetCurrentOrganization $getCurrentOrganization,
        YooKassaService $yookassa,
        PaymentLogger $logger,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($payment->organization_id !== $organization->id || $payment->provider !== 'yookassa') {
            throw new NotFoundHttpException;
        }

        if ($payment->status === 'paid') {
            return to_route('sites.index')
                ->with('success', 'Платеж уже подтвержден. Тариф активирован.');
        }

        if (! $yookassa->isConfigured()) {
            $logger->error('yookassa.checkout.not_configured', $payment, $request, 'ЮKassa не настроена: нет shopId или secret key.');

            return redirect()->route('billing.payments.show', $payment)
                ->with('error', 'ЮKassa не настроена. Укажите shopId и secret key в env-файле.');
        }

        try {
            $response = $yookassa->createPayment($payment, $request->user()?->email);
            $confirmationUrl = $yookassa->confirmationUrl($response);

            if ($confirmationUrl === null) {
                $logger->error('yookassa.checkout.no_confirmation_url', $payment, $request, 'ЮKassa не вернула ссылку подтверждения.', [
                    'yookassa_response' => $response,
                ]);

                return redirect()->route('billing.payments.show', $payment)
                    ->with('error', 'ЮKassa не вернула ссылку на оплату. Попробуйте позже.');
            }

            $payment->forceFill([
                'provider_payment_id' => is_scalar(data_get($response, 'id')) ? (string) data_get($response, 'id') : $payment->provider_payment_id,
                'payload' => array_replace_recursive(is_array($payment->payload) ? $payment->payload : [], [
                    'yookassa_create_response' => $response,
                    'yookassa_mode' => $yookassa->isTest() ? 'test' : 'prod',
                ]),
            ])->save();

            $logger->info('yookassa.checkout.created', $payment->refresh(), $request, 'Создан платеж ЮKassa, пользователь перенаправляется на оплату.', [
                'provider_payment_id' => $payment->provider_payment_id,
            ]);

            return redirect()->away($confirmationUrl);
        } catch (RequestException $exception) {
            $logger->error('yookassa.checkout.request_failed', $payment, $request, 'Ошибка API ЮKassa при создании платежа.', exception: $exception);

            return redirect()->route('billing.payments.show', $payment)
                ->with('error', 'ЮKassa временно недоступна. Попробуйте позже.');
        }
    }

    public function webhook(
        Request $request,
        YooKassaService $yookassa,
        CheckoutService $checkout,
        PaymentLogger $logger,
    ): Response {
        $payment = null;
        $payload = $yookassa->sanitizedPayload($request);

        try {
            if (! $yookassa->webhookIsValid($request)) {
                $logger->warning('yookassa.webhook.invalid_secret', null, $request, 'Webhook ЮKassa с некорректным секретом.', $payload);

                return response('Invalid webhook secret', 403);
            }

            $paymentId = $yookassa->paymentIdFromWebhook($payload);

            if ($paymentId === null) {
                $logger->warning('yookassa.webhook.invalid_payment_id', null, $request, 'Webhook ЮKassa без корректного metadata.payment_id.', $payload);

                return response('Bad payment id', 400);
            }

            $payment = Payment::query()
                ->with('subscription.plan')
                ->find($paymentId);

            if ($payment === null) {
                $logger->warning('yookassa.webhook.payment_not_found', null, $request, 'Webhook ЮKassa по неизвестному платежу.', $payload, [
                    'payment_id' => $paymentId,
                ]);

                return response('Payment not found', 404);
            }

            if ($payment->provider !== 'yookassa') {
                $logger->warning('yookassa.webhook.provider_mismatch', $payment, $request, 'Webhook ЮKassa пришел для платежа другого провайдера.', $payload);

                return response('Provider mismatch', 409);
            }

            $event = $yookassa->eventStatus($payload);

            if ($event === 'payment.succeeded') {
                $amountCents = $yookassa->amountCentsFromWebhook($payload);

                if ($amountCents === null || $amountCents !== $payment->amount_cents) {
                    $checkout->markFailed($payment, 'amount_mismatch', 'Сумма платежа ЮKassa не совпала с суммой в Montry.', [
                        'yookassa_webhook' => $payload,
                    ]);

                    $logger->error('yookassa.webhook.amount_mismatch', $payment, $request, 'Сумма ЮKassa не совпала с ожидаемой суммой платежа.', $payload, [
                        'expected_amount_cents' => $payment->amount_cents,
                        'received_amount_cents' => $amountCents,
                    ]);

                    return response('Amount mismatch', 422);
                }

                $checkout->confirm($payment, [
                    'yookassa_webhook' => $payload,
                    'yookassa_mode' => $yookassa->isTest() ? 'test' : 'prod',
                ], $yookassa->providerPaymentIdFromWebhook($payload));

                $logger->info('yookassa.webhook.processed', $payment->refresh(), $request, 'Платеж ЮKassa успешно обработан.', $payload);

                return response('OK', 200);
            }

            if (in_array($event, ['payment.canceled', 'refund.succeeded'], true)) {
                $checkout->markFailed($payment, 'payment_canceled', 'Платеж ЮKassa отменен или не завершен.', [
                    'yookassa_webhook' => $payload,
                ]);

                $logger->warning('yookassa.webhook.payment_canceled', $payment->refresh(), $request, 'ЮKassa сообщила об отмене платежа.', $payload);

                return response('OK', 200);
            }

            $logger->info('yookassa.webhook.ignored', $payment, $request, 'Webhook ЮKassa не требует изменения платежа.', $payload, [
                'event' => $event,
            ]);

            return response('OK', 200);
        } catch (Throwable $exception) {
            $logger->error('yookassa.webhook.exception', $payment, $request, 'Ошибка при обработке webhook ЮKassa.', $payload, exception: $exception);
            Log::error('YooKassa webhook processing failed', [
                'payment_id' => $payment?->id,
                'exception' => $exception::class,
            ]);

            return response('Temporary error', 500);
        }
    }

    public function return(
        Request $request,
        Payment $payment,
        YooKassaService $yookassa,
        CheckoutService $checkout,
        PaymentLogger $logger,
    ): RedirectResponse {
        $logger->info('yookassa.returned', $payment, $request, 'Пользователь вернулся из ЮKassa после оплаты.');

        if ($payment->status === 'pending'
            && $payment->provider === 'yookassa'
            && $payment->provider_payment_id !== null
            && $yookassa->isConfigured()) {
            try {
                $providerPayment = $yookassa->getPayment($payment->provider_payment_id);
                $providerStatus = $yookassa->paymentStatus($providerPayment);

                if ($providerStatus === 'succeeded') {
                    $amountCents = $yookassa->amountCents($providerPayment);

                    if ($amountCents === $payment->amount_cents) {
                        $checkout->confirm($payment, [
                            'yookassa_return_sync' => $providerPayment,
                            'yookassa_mode' => $yookassa->isTest() ? 'test' : 'prod',
                        ], $payment->provider_payment_id);

                        $logger->info('yookassa.return.synchronized', $payment->refresh(), $request, 'Платеж подтвержден при возврате из ЮKassa.', [
                            'provider_status' => $providerStatus,
                        ]);
                    } else {
                        $checkout->markFailed($payment, 'amount_mismatch', 'Сумма платежа ЮKassa не совпала с суммой в Montry.', [
                            'yookassa_return_sync' => $providerPayment,
                        ]);

                        $logger->error('yookassa.return.amount_mismatch', $payment->refresh(), $request, 'При возврате обнаружено несовпадение суммы платежа ЮKassa.', [
                            'expected_amount_cents' => $payment->amount_cents,
                            'received_amount_cents' => $amountCents,
                        ]);
                    }
                } elseif ($providerStatus === 'canceled') {
                    $checkout->markFailed($payment, 'payment_canceled', 'Платеж ЮKassa отменен или не завершен.', [
                        'yookassa_return_sync' => $providerPayment,
                    ]);

                    $logger->warning('yookassa.return.canceled', $payment->refresh(), $request, 'ЮKassa вернула отмененный платеж.', [
                        'provider_status' => $providerStatus,
                    ]);
                }

                $payment->refresh();
            } catch (RequestException $exception) {
                $logger->warning('yookassa.return.sync_failed', $payment, $request, 'Не удалось проверить статус платежа при возврате из ЮKassa.', context: [
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($payment->status === 'paid') {
            return to_route('sites.index')
                ->with('success', 'Платеж подтвержден. Тариф активирован.');
        }

        if ($payment->status === 'pending') {
            return to_route('sites.index')
                ->with('success', 'Оплата завершена. Тариф активируется после подтверждения от ЮKassa.');
        }

        if ($payment->failure_code === 'payment_canceled') {
            return $this->redirectCanceledPaymentToBilling($payment);
        }

        return redirect()->route('billing.payments.show', $payment)
            ->with('error', $payment->failure_reason ?: 'Оплата не была подтверждена.');
    }

    private function redirectCanceledPaymentToBilling(Payment $payment): RedirectResponse
    {
        $payment->loadMissing(['subscription.plan', 'subscription.items']);
        $planCode = $payment->subscription?->plan?->code;
        $addonQuantities = $payment->subscription?->items
            ? $payment->subscription->items
                ->mapWithKeys(fn ($item): array => [$item->code => (int) $item->quantity])
                ->all()
            : [];
        $isPlanCheckout = ($payment->payload['billing_mode'] ?? null) === 'plan_checkout';

        return to_route('billing.index', $isPlanCheckout && $planCode !== null ? ['plan' => $planCode] : [])
            ->with('billing.restored_addons', $addonQuantities)
            ->with('billing.checkout_notice', 'Вы отменили оплату. Проверьте тариф и дополнения, при необходимости скорректируйте заказ и попробуйте снова.');
    }
}
