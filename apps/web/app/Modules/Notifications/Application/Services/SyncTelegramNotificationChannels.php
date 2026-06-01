<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use Illuminate\Support\Collection;

final class SyncTelegramNotificationChannels
{
    private const INCIDENT_EVENT_TYPES = [
        'incident.opened',
        'incident.resolved',
    ];

    public function handle(User $user): void
    {
        $chatId = $this->chatId($user);
        $shouldEnable = (bool) $user->telegram_notifications_enabled && $chatId !== null;
        $activeOrganizationIds = $this->activeOrganizationIds($user);

        $this->disableChannelsOutsideActiveOrganizations($user, $activeOrganizationIds);

        foreach ($activeOrganizationIds as $organizationId) {
            $channel = $this->telegramChannel($user, $organizationId);

            $channel->forceFill([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'type' => 'telegram',
                'name' => $this->channelName($user),
                'enabled' => $shouldEnable,
                'settings' => [
                    'chat_id' => $chatId,
                    'username' => $user->telegram_username,
                    'source' => 'user_settings',
                    'event_types' => self::INCIDENT_EVENT_TYPES,
                ],
                'verified_at' => $shouldEnable ? ($user->telegram_connected_at ?? now()) : null,
            ])->save();
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function activeOrganizationIds(User $user): Collection
    {
        return $user->organizations()
            ->wherePivot('status', 'active')
            ->pluck('organizations.id')
            ->map(fn (mixed $id): int => (int) $id);
    }

    /**
     * @param Collection<int, int> $activeOrganizationIds
     */
    private function disableChannelsOutsideActiveOrganizations(User $user, Collection $activeOrganizationIds): void
    {
        $query = NotificationChannel::query()
            ->where('user_id', $user->id)
            ->where('type', 'telegram');

        if ($activeOrganizationIds->isNotEmpty()) {
            $query->whereNotIn('organization_id', $activeOrganizationIds->all());
        }

        $query->update(['enabled' => false]);
    }

    private function telegramChannel(User $user, int $organizationId): NotificationChannel
    {
        $channel = NotificationChannel::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('type', 'telegram')
            ->first();

        if ($channel === null) {
            return new NotificationChannel();
        }

        if ($channel->trashed()) {
            $channel->restore();
        }

        return $channel;
    }

    private function chatId(User $user): ?string
    {
        $chatId = trim((string) $user->telegram_chat_id);

        return $chatId !== '' ? $chatId : null;
    }

    private function channelName(User $user): string
    {
        $name = trim((string) ($user->telegram_username ?: $user->name));

        return $name !== '' ? "Telegram: {$name}" : 'Telegram';
    }
}
