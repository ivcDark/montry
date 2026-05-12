<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Senders\NotificationSenderInterface;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationLog;
use Illuminate\Support\Arr;
use Throwable;

final class NotificationDispatcher
{
    /**
     * @param array<int, NotificationSenderInterface> $senders
     */
    public function __construct(
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly array $senders,
    ) {
    }

    public function dispatch(NotificationMessage $message): void
    {
        if ($message->organizationId === null) {
            return;
        }

        $channels = $this->recipientResolver->resolve($message->organizationId, $message->eventType);

        foreach ($channels as $channel) {
            if ($this->alreadySent($channel, $message)) {
                continue;
            }

            $sender = $this->senderFor($channel);

            if ($sender === null) {
                continue;
            }

            try {
                $sender->send($channel, $message);

                $this->log($channel, $message, 'sent');
            } catch (Throwable $exception) {
                $this->log($channel, $message, 'failed', $exception->getMessage());
            }
        }
    }

    private function alreadySent(NotificationChannel $channel, NotificationMessage $message): bool
    {
        if ($message->incidentId === null) {
            return false;
        }

        return NotificationLog::query()
            ->where('notification_channel_id', $channel->id)
            ->where('incident_id', $message->incidentId)
            ->where('event_type', $message->eventType)
            ->where('status', 'sent')
            ->exists();
    }

    private function senderFor(NotificationChannel $channel): ?NotificationSenderInterface
    {
        return Arr::first(
            $this->senders,
            fn (NotificationSenderInterface $sender): bool => $sender->supports($channel),
        );
    }

    private function log(
        NotificationChannel $channel,
        NotificationMessage $message,
        string $status,
        ?string $errorMessage = null,
    ): void {
        NotificationLog::query()->create([
            'organization_id' => $message->organizationId,
            'notification_channel_id' => $channel->id,
            'incident_id' => $message->incidentId,
            'event_type' => $message->eventType,
            'status' => $status,
            'payload' => $message->payload,
            'error_message' => $errorMessage,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
