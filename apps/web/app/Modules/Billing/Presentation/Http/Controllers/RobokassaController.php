<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\CheckoutService;
use App\Modules\Billing\Application\Services\PaymentLogger;
use App\Modules\Billing\Application\Services\RobokassaService;
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class RobokassaController extends Controller
{
    public function result(
        Request $request,
        RobokassaService $robokassa,
        CheckoutService $checkout,
        PaymentLogger $logger,
    ): Response {
        $payment = null;
        $payload = $robokassa->sanitizedPayload($request);

        try {
            $paymentId = $robokassa->paymentIdFromRequest($request);

            if ($paymentId === null) {
                $logger->warning('robokassa.result.invalid_inv_id', null, $request, 'Robokassa ResultURL без корректного InvId.', $payload);

                return response('Bad InvId', 400);
            }

            $payment = Payment::query()
                ->with('subscription.plan')
                ->find($paymentId);

            if ($payment === null) {
                $logger->warning('robokassa.result.payment_not_found', null, $request, 'Robokassa ResultURL по неизвестному платежу.', $payload, [
                    'payment_id' => $paymentId,
                ]);

                return response('Payment not found', 404);
            }

            if ($payment->provider !== 'robokassa') {
                $logger->warning('robokassa.result.provider_mismatch', $payment, $request, 'ResultURL Robokassa пришел для платежа другого провайдера.', $payload);

                return response('Provider mismatch', 409);
            }

            $logger->info('robokassa.result.received', $payment, $request, 'Получен ResultURL от Robokassa.', $payload);

            if (! $robokassa->isConfigured()) {
                $logger->error('robokassa.result.not_configured', $payment, $request, 'Robokassa не настроена: нет MerchantLogin или паролей.', $payload);

                return response('Robokassa is not configured', 500);
            }

            if (! $robokassa->resultSignatureIsValid($request)) {
                $logger->error('robokassa.result.invalid_signature', $payment, $request, 'Некорректная подпись ResultURL Robokassa.', $payload);

                return response('Invalid signature', 403);
            }

            $amountCents = $robokassa->amountCentsFromRequest($request);

            if ($amountCents === null || $amountCents !== $payment->amount_cents) {
                $checkout->markFailed($payment, 'amount_mismatch', 'Сумма платежа Robokassa не совпала с суммой в Montry.', [
                    'robokassa_result' => $payload,
                ]);

                $logger->error('robokassa.result.amount_mismatch', $payment, $request, 'Сумма Robokassa не совпала с ожидаемой суммой платежа.', $payload, [
                    'expected_amount_cents' => $payment->amount_cents,
                    'received_amount_cents' => $amountCents,
                ]);

                return response('Amount mismatch', 422);
            }

            $checkout->confirm($payment, [
                'robokassa_result' => $payload,
                'robokassa_mode' => $robokassa->isTest() ? 'test' : 'prod',
            ], $robokassa->providerPaymentIdFromRequest($request));

            $logger->info('robokassa.result.processed', $payment->refresh(), $request, 'Платеж Robokassa успешно обработан.', $payload);

            return response('OK'.$payment->id, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (Throwable $exception) {
            $logger->error('robokassa.result.exception', $payment, $request, 'Ошибка при обработке ResultURL Robokassa.', $payload, exception: $exception);
            Log::error('Robokassa ResultURL processing failed', [
                'payment_id' => $payment?->id,
                'exception' => $exception::class,
            ]);

            return response('Temporary error', 500);
        }
    }

    public function success(
        Request $request,
        RobokassaService $robokassa,
        CheckoutService $checkout,
        PaymentLogger $logger,
    ): RedirectResponse {
        $payment = $this->findPaymentFromRequest($request, $robokassa);
        $payload = $robokassa->sanitizedPayload($request);

        if ($payment === null) {
            $logger->warning('robokassa.success.payment_not_found', null, $request, 'SuccessURL Robokassa без найденного платежа.', $payload);

            return to_route('billing.index')
                ->with('error', 'Платеж не найден. Если деньги были списаны, напишите в поддержку.');
        }

        if ($payment->provider !== 'robokassa') {
            $logger->warning('robokassa.success.provider_mismatch', $payment, $request, 'SuccessURL Robokassa пришел для платежа другого провайдера.', $payload);

            return to_route('billing.index')
                ->with('error', 'Платеж относится к другому платежному провайдеру.');
        }

        if ($robokassa->isConfigured() && ! $robokassa->successSignatureIsValid($request)) {
            $logger->warning('robokassa.success.invalid_signature', $payment, $request, 'Некорректная подпись SuccessURL Robokassa.', $payload);

            return redirect()->route('billing.payments.show', $payment)
                ->with('error', 'Не удалось проверить возврат из Robokassa. Статус платежа будет обновлен по серверному уведомлению.');
        }

        $logger->info('robokassa.success.returned', $payment, $request, 'Пользователь вернулся из Robokassa после успешной оплаты.', $payload);

        if ($payment->status === 'paid') {
            return to_route('sites.index')
                ->with('success', 'Платеж подтвержден. Тариф активирован.');
        }

        if (! $robokassa->isConfigured()) {
            $logger->warning('robokassa.success.not_configured', $payment, $request, 'SuccessURL Robokassa получен, но Robokassa не настроена для проверки подписи.', $payload);

            return redirect()->route('billing.payments.show', $payment)
                ->with('success', 'Оплата завершена. Ожидаем серверное подтверждение от Robokassa.');
        }

        $amountCents = $robokassa->amountCentsFromRequest($request);

        if ($amountCents === null || $amountCents !== $payment->amount_cents) {
            $checkout->markFailed($payment, 'amount_mismatch', 'Сумма платежа Robokassa на SuccessURL не совпала с суммой в Montry.', [
                'robokassa_success' => $payload,
            ]);

            $logger->error('robokassa.success.amount_mismatch', $payment->refresh(), $request, 'Сумма SuccessURL Robokassa не совпала с ожидаемой суммой платежа.', $payload, [
                'expected_amount_cents' => $payment->amount_cents,
                'received_amount_cents' => $amountCents,
            ]);

            return redirect()->route('billing.payments.show', $payment)
                ->with('error', 'Сумма платежа не совпала. Напишите в поддержку, если деньги были списаны.');
        }

        $checkout->confirm($payment, [
            'robokassa_success' => $payload,
            'robokassa_mode' => $robokassa->isTest() ? 'test' : 'prod',
        ], $robokassa->providerPaymentIdFromRequest($request));

        $logger->info('robokassa.success.processed', $payment->refresh(), $request, 'Платеж Robokassa подтвержден по валидному SuccessURL.', $payload);

        return to_route('sites.index')
            ->with('success', 'Платеж подтвержден. Тариф активирован.');
    }

    public function fail(
        Request $request,
        RobokassaService $robokassa,
        CheckoutService $checkout,
        PaymentLogger $logger,
    ): RedirectResponse {
        $payment = $this->findPaymentFromRequest($request, $robokassa);
        $payload = $robokassa->sanitizedPayload($request);

        $logger->warning('robokassa.fail.returned', $payment, $request, 'Пользователь вернулся из Robokassa после отказа или ошибки оплаты.', $payload);

        if ($payment === null) {
            return to_route('billing.index')
                ->with('error', 'Платеж не завершен. Вы можете выбрать тариф и попробовать оплатить снова.');
        }

        if ($payment->provider !== 'robokassa') {
            return to_route('billing.index')
                ->with('error', 'Платеж относится к другому платежному провайдеру.');
        }

        if ($payment->status === 'pending') {
            $checkout->markFailed($payment, 'robokassa_fail_return', 'Пользователь вернулся из Robokassa после отказа или ошибки оплаты.', [
                'robokassa_fail' => $payload,
            ]);
        }

        return redirect()->route('billing.payments.show', $payment)
            ->with('error', 'Платеж не завершен. Можно повторить оплату.');
    }

    public function testSuccess(
        Request $request,
        Payment $payment,
        GetCurrentOrganization $getCurrentOrganization,
        RobokassaService $robokassa,
        CheckoutService $checkout,
        PaymentLogger $logger,
    ): RedirectResponse {
        if (! $robokassa->isTest()) {
            throw new NotFoundHttpException;
        }

        $organization = $getCurrentOrganization->handle($request->user());

        if ($payment->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        $checkout->confirm($payment, [
            'robokassa_test_result' => [
                'simulated' => true,
                'confirmed_at' => now()->toISOString(),
            ],
            'robokassa_mode' => 'test',
        ], 'test-'.$payment->id);

        $logger->info('robokassa.test_success.processed', $payment->refresh(), $request, 'Фиктивный тестовый платеж Robokassa подтвержден из интерфейса Montry.', [
            'simulated' => true,
        ]);

        return to_route('sites.index')
            ->with('success', 'Тестовый платеж подтвержден. Тариф активирован.');
    }

    private function findPaymentFromRequest(Request $request, RobokassaService $robokassa): ?Payment
    {
        $paymentId = $robokassa->paymentIdFromRequest($request);

        if ($paymentId === null) {
            return null;
        }

        return Payment::query()->find($paymentId);
    }
}
