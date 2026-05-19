<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class RegistrationCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Регистрация в Montry завершена')
            ->view('emails.auth.registration-completed');
    }
}
