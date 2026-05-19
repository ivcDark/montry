<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\Infrastructure\Persistence\Models\EmailVerificationCode;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class ResendRegistrationVerificationCode
{
    public function __construct(
        private StartRegistrationVerification $startRegistrationVerification,
    ) {}

    public function handle(User $user): void
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'code' => 'Email уже подтвержден.',
            ]);
        }

        $activeCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($activeCode && $activeCode->last_sent_at->diffInSeconds(now()) < $this->cooldownSeconds()) {
            $secondsLeft = $this->cooldownSeconds() - $activeCode->last_sent_at->diffInSeconds(now());

            throw ValidationException::withMessages([
                'code' => "Повторно отправить код можно через {$secondsLeft} секунд.",
            ]);
        }

        EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->startRegistrationVerification->sendCode($user);
    }

    private function cooldownSeconds(): int
    {
        return (int) config('auth.email_verification.resend_cooldown_seconds', 120);
    }
}
