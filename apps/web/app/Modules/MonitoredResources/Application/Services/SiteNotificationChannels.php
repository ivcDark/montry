<?php

namespace App\Modules\MonitoredResources\Application\Services;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SiteNotificationChannels
{
    /** @var array<int, string> */
    public const CHANNELS = ['email', 'telegram', 'max'];

    /**
     * @return array<string, bool>
     */
    public function settings(MonitoredResource $site): array
    {
        $settings = is_array($site->notification_channels) ? $site->notification_channels : [];

        return collect(self::CHANNELS)
            ->mapWithKeys(fn (string $channel): array => [$channel => (bool) ($settings[$channel] ?? true)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function enabledForSite(MonitoredResource $site): array
    {
        return collect($this->settings($site))
            ->filter(fn (bool $enabled): bool => $enabled)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public function connectedTypes(int $organizationId): Collection
    {
        $types = NotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->whereIn('type', self::CHANNELS)
            ->pluck('type')
            ->unique()
            ->values();

        if (! $types->contains('email') && $this->organizationHasActiveEmailUser($organizationId)) {
            $types->push('email');
        }

        return $types->unique()->values();
    }

    /**
     * @return array<int, array{type: string, label: string, enabled: bool, connected: bool, active: bool}>
     */
    public function payload(MonitoredResource $site, ?Collection $connectedTypes = null): array
    {
        $settings = $this->settings($site);
        $connectedTypes ??= $this->connectedTypes((int) $site->organization_id);

        return collect(self::CHANNELS)
            ->map(fn (string $channel): array => [
                'type' => $channel,
                'label' => $this->label($channel),
                'enabled' => $settings[$channel] ?? true,
                'connected' => $connectedTypes->contains($channel),
                'active' => ($settings[$channel] ?? true) && $connectedTypes->contains($channel),
            ])
            ->values()
            ->all();
    }

    private function organizationHasActiveEmailUser(int $organizationId): bool
    {
        return DB::table('organization_users')
            ->join('users', 'users.id', '=', 'organization_users.user_id')
            ->where('organization_users.organization_id', $organizationId)
            ->where('organization_users.status', 'active')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->exists();
    }

    private function label(string $channel): string
    {
        return match ($channel) {
            'email' => 'Email',
            'telegram' => 'Telegram',
            'max' => 'Max',
            default => ucfirst($channel),
        };
    }
}