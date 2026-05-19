<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTO\RegisterUserData;
use App\Modules\Auth\Infrastructure\Persistence\Models\EmailVerificationCode;
use App\Modules\Auth\Mail\RegistrationVerificationCodeMail;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final readonly class StartRegistrationVerification
{
    public function __construct(
        private RegisterUser $registerUser,
    ) {}

    public function handle(RegisterUserData $data): User
    {
        $user = $this->registerUser->handle($data);

        $this->sendCode($user);

        return $user;
    }

    public function sendCode(User $user): void
    {
        $code = $this->generateCode();

        EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        Mail::to($user->email)->send(new RegistrationVerificationCodeMail($code));
    }

    private function generateCode(): string
    {
        $max = (10 ** $this->codeLength()) - 1;

        return str_pad((string) random_int(0, $max), $this->codeLength(), '0', STR_PAD_LEFT);
    }

    private function codeLength(): int
    {
        return (int) config('auth.email_verification.code_length', 5);
    }

    private function ttlMinutes(): int
    {
        return (int) config('auth.email_verification.ttl_minutes', 10);
    }
}
