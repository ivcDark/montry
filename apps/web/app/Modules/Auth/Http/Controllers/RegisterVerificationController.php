<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\ResendRegistrationVerificationCode;
use App\Modules\Auth\Actions\VerifyRegistrationEmailCode;
use App\Modules\Auth\Http\Requests\VerifyRegistrationCodeRequest;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterVerificationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        return Inertia::render('Auth/VerifyRegistrationCode', [
            'email' => $user->email,
            'resendCooldownSeconds' => (int) config('auth.email_verification.resend_cooldown_seconds', 120),
        ]);
    }

    public function store(
        VerifyRegistrationCodeRequest $request,
        VerifyRegistrationEmailCode $verifyRegistrationEmailCode,
    ): RedirectResponse {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        $verifyRegistrationEmailCode->handle($user, $request->string('code')->toString());

        $request->session()->forget('pending_registration_user_id');
        $request->session()->regenerate();

        return redirect()->route('dashboard.index');
    }

    public function resend(
        Request $request,
        ResendRegistrationVerificationCode $resendRegistrationVerificationCode,
    ): RedirectResponse {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        $resendRegistrationVerificationCode->handle($user);

        return redirect()
            ->route('register.verify-code')
            ->with('success', 'Новый код отправлен на email.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('pending_registration_user_id');

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }
}
