<?php

namespace App\Modules\Feedback\Application\Commands;

final readonly class SendFeedbackMessageCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $message,
        public ?string $subject = null,
        public string $source = 'landing',
        public ?string $pageUrl = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?int $userId = null,
        public ?string $userName = null,
        public ?string $userEmail = null,
        public ?int $organizationId = null,
        public ?string $organizationName = null,
    ) {}
}
