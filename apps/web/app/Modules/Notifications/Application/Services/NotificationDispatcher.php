<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Senders\NotificationSenderInterface;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationLog;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Observability\Application\Services\DeadLetterRecorder;
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
        private readonly BusinessEventRecorder $events,
        private readonly DeadLetterRecorder $deadLetters,
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

                $log = $this->log($channel, $message, 'sent');
                $this->recordNotificationEvent($channel, $message, $log, 'sent');
            } catch (Throwable $exception) {
                $log = $this->log($channel, $message, 'failed', $exception->getMessage());
                $this->recordNotificationEvent($channel, $message, $log, 'failed', $exception->getMessage());
                $this->deadLetters->record(
                    source: 'notifications',
                    type: 'notification_delivery',
                    exception: $exception,
                    recoverable: false,
                    idempotencyKey: "notification_log:{$log->id}",
                    organizationId: $message->organizationId,
                    subjectType: 'notification_log',
                    subjectId: (string) $log->id,
                    payload: [
                        'notification_log_id' => $log->id,
                        'notification_channel_id' => $channel->id,
                        'channel_type' => $channel->type,
                        'incident_id' => $message->incidentId,
                        'event_type' => $message->eventType,
                    ],
                    context: [
                        'delivery_attempts_exhausted' => true,
                        'max_attempts' => 1,
                    ],
                    attempts: 1,
                    maxAttempts: 1,
                );
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
    ): NotificationLog {
        return NotificationLog::query()->create([
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

    private function recordNotificationEvent(
        NotificationChannel $channel,
        NotificationMessage $message,
        NotificationLog $log,
        string $status,
        ?string $errorMessage = null,
    ): void {
        $this->events->record(new RecordBusinessEventData(
            eventType: "notification.{$channel->type}.{$status}",
            organizationId: $message->organizationId,
            subjectType: 'notification_log',
            subjectId: (string) $log->id,
            status: $status,
            source: 'notifications',
            payload: [
                'notification_channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'incident_id' => $message->incidentId,
                'notification_event_type' => $message->eventType,
                'error_message' => $errorMessage,
            ],
        ));
    }
}
