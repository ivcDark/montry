<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\AuthenticateYandexUser;
use App\Modules\Auth\Services\YandexOAuthClient;
use App\Modules\Billing\Application\Services\PlanIntentService;
use App\Modules\Billing\Application\Services\StartIntendedCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class YandexAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'auth.yandex.state';

    public function redirect(
        Request $request,
        PlanIntentService $planIntent,
        YandexOAuthClient $client,
    ): RedirectResponse {
        $planIntent->captureFromRequest($request);

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($client->authorizationUrl($state));
    }

    public function callback(
        Request $request,
        YandexOAuthClient $client,
        AuthenticateYandexUser $authenticateYandexUser,
        StartIntendedCheckout $startIntendedCheckout,
    ): RedirectResponse {
        if ($request->filled('error')) {
            return $this->redirectWithError('Авторизация через Яндекс была отменена или завершилась ошибкой.');
        }

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $actualState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($actualState) || ! hash_equals($expectedState, $actualState)) {
            return $this->redirectWithError('Не удалось проверить ответ Яндекса. Попробуйте войти снова.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->redirectWithError('Яндекс не передал код авторизации.');
        }

        try {
            $user = $authenticateYandexUser->handle($client->userFromCode($code));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        if ((bool) $user->is_blocked) {
            return $this->redirectWithError('Учетная запись заблокирована.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $startIntendedCheckout->redirect($request, $user);
    }

    private function redirectWithError(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors([
                'yandex' => $message,
            ]);
    }
}
