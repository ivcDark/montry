<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Identity\Presentation\Http\Requests\UpdateMaxSettingsRequest;
use App\Modules\Identity\Presentation\Http\Requests\UpdateProfileSettingsRequest;
use App\Modules\Identity\Presentation\Http\Requests\UpdateTelegramSettingsRequest;
use App\Modules\Notifications\Application\Services\SyncMaxNotificationChannels;
use App\Modules\Notifications\Application\Services\SyncTelegramNotificationChannels;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class UserSettingsController extends Controller
{
    public function index(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): Response {
        $user = $request->user();
        $organization = $getCurrentOrganization->handle($user);
        $telegramIsAvailable = $limits->canUseNotificationChannel((int) $organization->id, 'telegram');
        $maxIsAvailable = $limits->canUseNotificationChannel((int) $organization->id, 'max');

        return Inertia::render('Settings/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'settings' => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'telegram' => [
                    'notifications_enabled' => (bool) $user->telegram_notifications_enabled,
                    'is_connected' => $user->telegram_connected_at !== null && $user->telegram_chat_id !== null,
                    'username' => $user->telegram_username,
                    'connected_at' => $user->telegram_connected_at?->toISOString(),
                    'connection_token' => $user->telegram_connection_token,
                    'bot_username' => $this->telegramBotUsername(),
                    'setup_url' => $this->telegramSetupUrl($user),
                    'is_available' => $telegramIsAvailable,
                ],
                'max' => [
                    'notifications_enabled' => (bool) $user->max_notifications_enabled,
                    'is_connected' => $user->max_connected_at !== null && $user->max_chat_id !== null,
                    'username' => $user->max_username,
                    'connected_at' => $user->max_connected_at?->toISOString(),
                    'connection_token' => $user->max_connection_token,
                    'bot_username' => $this->maxBotUsername(),
                    'setup_url' => $this->maxSetupUrl($user),
                    'is_available' => $maxIsAvailable,
                ],
            ],
        ]);
    }

    public function updateProfile(UpdateProfileSettingsRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'name' => $request->string('name')->trim()->toString(),
        ])->save();

        return redirect()
            ->route('settings.index')
            ->with('success', 'Настройки профиля сохранены.');
    }

    public function updateTelegram(
        UpdateTelegramSettingsRequest $request,
        SyncTelegramNotificationChannels $syncTelegramChannels,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): RedirectResponse {
        $user = $request->user();
        $notificationsEnabled = $request->boolean('telegram_notifications_enabled');

        if ($notificationsEnabled && ! $this->channelIsAvailable($user, 'telegram', $getCurrentOrganization, $limits)) {
            return $this->channelUnavailableRedirect('Telegram');
        }

        $this->saveTelegramSettings($user, $notificationsEnabled);

        $syncTelegramChannels->handle($user->refresh());

        return redirect()
            ->route('settings.index')
            ->with('success', 'Настройки Telegram сохранены.');
    }

    public function confirmTelegram(
        UpdateTelegramSettingsRequest $request,
        SyncTelegramNotificationChannels $syncTelegramChannels,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): RedirectResponse|SymfonyResponse {
        $user = $request->user();
        $notificationsEnabled = $request->boolean('telegram_notifications_enabled');

        if ($notificationsEnabled && ! $this->channelIsAvailable($user, 'telegram', $getCurrentOrganization, $limits)) {
            return $this->channelUnavailableRedirect('Telegram');
        }

        $this->saveTelegramSettings($user, $notificationsEnabled);

        $syncTelegramChannels->handle($user->refresh());

        if (! $notificationsEnabled) {
            return redirect()
                ->route('settings.index')
                ->with('success', 'Настройки Telegram сохранены.');
        }

        $setupUrl = $this->telegramSetupUrl($user);

        if ($setupUrl === null) {
            return redirect()
                ->route('settings.index')
                ->with('error', 'Telegram-бот не настроен. Укажите TELEGRAM_BOT_USERNAME в окружении.');
        }

        return Inertia::location($setupUrl);
    }

    public function updateMax(
        UpdateMaxSettingsRequest $request,
        SyncMaxNotificationChannels $syncMaxChannels,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): RedirectResponse {
        $user = $request->user();
        $notificationsEnabled = $request->boolean('max_notifications_enabled');

        if ($notificationsEnabled && ! $this->channelIsAvailable($user, 'max', $getCurrentOrganization, $limits)) {
            return $this->channelUnavailableRedirect('Max');
        }

        $this->saveMaxSettings($user, $notificationsEnabled);

        $syncMaxChannels->handle($user->refresh());

        return redirect()
            ->route('settings.index')
            ->with('success', 'Настройки Max сохранены.');
    }

    public function confirmMax(
        UpdateMaxSettingsRequest $request,
        SyncMaxNotificationChannels $syncMaxChannels,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): RedirectResponse|SymfonyResponse {
        $user = $request->user();
        $notificationsEnabled = $request->boolean('max_notifications_enabled');

        if ($notificationsEnabled && ! $this->channelIsAvailable($user, 'max', $getCurrentOrganization, $limits)) {
            return $this->channelUnavailableRedirect('Max');
        }

        $this->saveMaxSettings($user, $notificationsEnabled);

        $syncMaxChannels->handle($user->refresh());

        if (! $notificationsEnabled) {
            return redirect()
                ->route('settings.index')
                ->with('success', 'Настройки Max сохранены.');
        }

        $setupUrl = $this->maxSetupUrl($user);

        if ($setupUrl === null) {
            return redirect()
                ->route('settings.index')
                ->with('error', 'Max-бот не настроен. Укажите MAX_BOT_URL или MAX_BOT_USERNAME в окружении.');
        }

        return Inertia::location($setupUrl);
    }

    private function saveTelegramSettings(User $user, bool $notificationsEnabled): void
    {
        $user->forceFill([
            'telegram_notifications_enabled' => $notificationsEnabled,
            'telegram_connection_token' => $user->telegram_connection_token ?: $this->newConnectionToken('telegram_connection_token'),
        ])->save();
    }

    private function saveMaxSettings(User $user, bool $notificationsEnabled): void
    {
        $user->forceFill([
            'max_notifications_enabled' => $notificationsEnabled,
            'max_connection_token' => $user->max_connection_token ?: $this->newConnectionToken('max_connection_token'),
        ])->save();
    }

    private function telegramBotUsername(): ?string
    {
        $username = trim((string) config('services.telegram.bot_username', ''));

        return $username !== '' ? ltrim($username, '@') : null;
    }

    private function maxBotUsername(): ?string
    {
        $username = trim((string) config('services.max.bot_username', ''));

        return $username !== '' ? ltrim($username, '@') : null;
    }

    private function telegramSetupUrl(User $user): ?string
    {
        $botUsername = $this->telegramBotUsername();

        if ($botUsername === null || $user->telegram_connection_token === null) {
            return null;
        }

        return "https://t.me/{$botUsername}?start=".rawurlencode($user->telegram_connection_token);
    }

    private function maxSetupUrl(User $user): ?string
    {
        if ($user->max_connection_token === null) {
            return null;
        }

        $configuredUrl = trim((string) config('services.max.bot_url', ''));

        if ($configuredUrl !== '') {
            $separator = str_contains($configuredUrl, '?') ? '&' : '?';

            return $configuredUrl.$separator.'start='.rawurlencode($user->max_connection_token);
        }

        $botUsername = $this->maxBotUsername();

        if ($botUsername === null) {
            return null;
        }

        return "https://max.ru/{$botUsername}?start=".rawurlencode($user->max_connection_token);
    }

    private function newConnectionToken(string $column): string
    {
        do {
            $token = Str::random(48);
        } while (User::query()->where($column, $token)->exists());

        return $token;
    }

    private function channelIsAvailable(
        User $user,
        string $channel,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): bool {
        $organization = $getCurrentOrganization->handle($user);

        return $limits->canUseNotificationChannel((int) $organization->id, $channel);
    }

    private function channelUnavailableRedirect(string $channelLabel): RedirectResponse
    {
        return redirect()
            ->route('settings.index')
            ->with('error', "{$channelLabel} доступен только на подписке Pro и Team.");
    }
}