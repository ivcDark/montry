<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Monitoring\Domain\Events\SslExpiring;
use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;

final readonly class SendSslExpiringNotification
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(SslExpiring $event): void
    {
        $this->dispatcher->dispatch(new NotificationMessage(
            eventType: 'ssl.expiring',
            subject: 'SSL certificate expiring: ' . $event->domain,
            body: "SSL certificate for {$event->domain} expires in {$event->daysUntilExpiration} days.",
            payload: [
                'monitor_id' => $event->monitorId,
                'domain' => $event->domain,
                'days_until_expiration' => $event->daysUntilExpiration,
                'expires_at' => $event->expiresAt?->format(DATE_ATOM),
            ],
            organizationId: $event->organizationId,
        ));
    }
}
