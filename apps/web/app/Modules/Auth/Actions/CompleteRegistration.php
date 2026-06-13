<?php

namespace App\Modules\Auth\Actions;

use App\Application\Onboarding\Actions\CompleteAccountRegistration;
use App\Modules\Auth\Infrastructure\Persistence\Models\EmailVerificationCode;
use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final readonly class CompleteRegistration
{
    public function __construct(
        private CompleteAccountRegistration $completeAccountRegistration,
        private BusinessEventRecorder $events,
    ) {}

    public function handle(User $user, ?EmailVerificationCode $verificationCode = null): void
    {
        DB::transaction(function () use ($user, $verificationCode): void {
            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($verificationCode !== null) {
                $verificationCode->forceFill([
                    'consumed_at' => now(),
                ])->save();
            }

            $this->completeAccountRegistration->handle($user);
        });

        Mail::to($user->email)->send(new RegistrationCompletedMail($user->name));

        $this->events->record(new RecordBusinessEventData(
            eventType: 'registration.completed',
            userId: $user->id,
            subjectType: 'user',
            subjectId: (string) $user->id,
            status: 'success',
            source: 'web',
        ));

        Auth::login($user);
    }
}
