<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Auth\Mail\RegistrationVerificationCodeMail;
use Tests\TestCase;

final class RegistrationVerificationCodeMailTest extends TestCase
{
    public function test_registration_verification_code_email_uses_montry_branded_design(): void
    {
        $html = (new RegistrationVerificationCodeMail('12345'))->render();

        $this->assertStringContainsString('Montry', $html);
        $this->assertStringContainsString('12345', $html);
        $this->assertStringContainsString('#123D2B', $html);
        $this->assertStringContainsString('#24A869', $html);
        $this->assertStringContainsString('Код действует 10 минут', $html);
    }

    public function test_registration_completed_email_uses_montry_branded_design(): void
    {
        $html = (new RegistrationCompletedMail('Ivan Petrov'))->render();

        $this->assertStringContainsString('Montry', $html);
        $this->assertStringContainsString('Ivan Petrov', $html);
        $this->assertStringContainsString('#123D2B', $html);
        $this->assertStringContainsString('#24A869', $html);
        $this->assertStringContainsString('Открыть кабинет', $html);
    }
}
