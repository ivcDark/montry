<?php

namespace App\Modules\Auth\Actions;

use App\Application\Onboarding\Actions\CompleteAccountRegistration;
use App\Modules\Auth\Infrastructure\Persistence\Models\EmailVerificationCode;
use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final readonly class VerifyRegistrationEmailCode
{
    public function __construct(
        private CompleteAccountRegistration $completeAccountRegistration,
    ) {}

    public function handle(User $user, string $code): void
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'code' => 'Email уже подтвержден.',
            ]);
        }

        $verificationCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $verificationCode) {
            throw ValidationException::withMessages([
                'code' => 'Код подтверждения не найден.',
            ]);
        }

        if ($verificationCode->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => 'Код подтверждения истек.',
            ]);
        }

        if ($verificationCode->attempts >= $this->maxAttempts()) {
            throw ValidationException::withMessages([
                'code' => 'Превышено количество попыток. Отправьте новый код.',
            ]);
        }

        if (! Hash::check($code, $verificationCode->code_hash)) {
            $verificationCode->increment('attempts');

            throw ValidationException::withMessages([
                'code' => 'Неверный код подтверждения.',
            ]);
        }

        DB::transaction(function () use ($user, $verificationCode): void {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $verificationCode->forceFill([
                'consumed_at' => now(),
            ])->save();

            $this->completeAccountRegistration->handle($user);
        });

        Mail::to($user->email)->send(new RegistrationCompletedMail($user->name));

        Auth::login($user);
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.email_verification.max_attempts', 5);
    }
}
