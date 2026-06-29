<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use Illuminate\Support\Collection;

final class SyncEmailNotificationChannels
{
    private const EVENT_TYPES = [
        'incident.opened',
        'incident.resolved',
        'ssl.expiring',
        'domain.expiring',
    ];

    public function handleOrganization(int $organizationId): void
    {
        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            return;
        }

        $activeUsers = $organization->users()
            ->wherePivot('status', 'active')
            ->get(['users.id', 'users.name', 'users.email', 'users.email_verified_at']);

        $activeUserIds = $activeUsers->pluck('id')->map(fn (mixed $id): int => (int) $id);

        $this->disableChannelsForInactiveUsers($organizationId, $activeUserIds);

        foreach ($activeUsers as $user) {
            $this->syncUserChannel($organizationId, $user);
        }
    }

    /**
     * @param Collection<int, int> $activeUserIds
     */
    private function disableChannelsForInactiveUsers(int $organizationId, Collection $activeUserIds): void
    {
        $query = NotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('type', 'email');

        if ($activeUserIds->isNotEmpty()) {
            $query->whereNotIn('user_id', $activeUserIds->all());
        }

        $query->update(['enabled' => false]);
    }

    private function syncUserChannel(int $organizationId, User $user): void
    {
        $email = trim((string) $user->email);
        $channel = $this->emailChannel($organizationId, (int) $user->id);
        $shouldEnable = $email !== '';

        $channel->forceFill([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'type' => 'email',
            'name' => $this->channelName($user, $email),
            'enabled' => $shouldEnable,
            'settings' => [
                'email' => $email,
                'source' => 'user_account',
                'event_types' => self::EVENT_TYPES,
            ],
            'verified_at' => $shouldEnable ? ($user->email_verified_at ?? now()) : null,
        ])->save();
    }

    private function emailChannel(int $organizationId, int $userId): NotificationChannel
    {
        $channel = NotificationChannel::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('type', 'email')
            ->first();

        if ($channel === null) {
            return new NotificationChannel;
        }

        if ($channel->trashed()) {
            $channel->restore();
        }

        return $channel;
    }

    private function channelName(User $user, string $email): string
    {
        $name = trim((string) $user->name);

        return $name !== '' ? "Email: {$name}" : "Email: {$email}";
    }
}