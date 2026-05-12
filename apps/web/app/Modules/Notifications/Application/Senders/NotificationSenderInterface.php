<?php

namespace App\Modules\Notifications\Application\Senders;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;

interface NotificationSenderInterface
{
    public function supports(NotificationChannel $channel): bool;

    public function send(NotificationChannel $channel, NotificationMessage $message): void;
}
