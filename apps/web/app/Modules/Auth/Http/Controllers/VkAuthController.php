<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\AuthenticateVkUser;
use App\Modules\Auth\Services\VkOAuthClient;
use App\Modules\Billing\Application\Services\PlanIntentService;
use App\Modules\Billing\Application\Services\StartIntendedCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VkAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'auth.vk.state';

    public function redirect(
        Request $request,
        PlanIntentService $planIntent,
        VkOAuthClient $client,
    ): RedirectResponse {
        $planIntent->captureFromRequest($request);

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($client->authorizationUrl($state));
    }

    public function callback(
        Request $request,
        VkOAuthClient $client,
        AuthenticateVkUser $authenticateVkUser,
        StartIntendedCheckout $startIntendedCheckout,
    ): RedirectResponse {
        if ($request->filled('error')) {
            return $this->redirectWithError('Авторизация через VK была отменена или завершилась ошибкой.');
        }

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $actualState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($actualState) || ! hash_equals($expectedState, $actualState)) {
            return $this->redirectWithError('Не удалось проверить ответ VK. Попробуйте войти снова.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->redirectWithError('VK не передал код авторизации.');
        }

        try {
            $user = $authenticateVkUser->handle($client->userFromCode($code));
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
                'vk' => $message,
            ]);
    }
}
