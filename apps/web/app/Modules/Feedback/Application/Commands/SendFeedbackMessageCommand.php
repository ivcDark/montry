<?php

namespace App\Modules\Feedback\Application\Commands;

final readonly class SendFeedbackMessageCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $message,
        public ?string $pageUrl = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
