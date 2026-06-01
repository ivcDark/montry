<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Identity\Presentation\Http\Requests\UpdateProfileSettingsRequest;
use App\Modules\Identity\Presentation\Http\Requests\UpdateTelegramSettingsRequest;
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
    public function index(Request $request, GetCurrentOrganization $getCurrentOrganization): Response
    {
        $user = $request->user();
        $organization = $getCurrentOrganization->handle($user);

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
    ): RedirectResponse {
        $user = $request->user();

        $this->saveTelegramSettings($user, $request->boolean('telegram_notifications_enabled'));

        $syncTelegramChannels->handle($user->refresh());

        return redirect()
            ->route('settings.index')
            ->with('success', 'Настройки Telegram сохранены.');
    }

    public function confirmTelegram(
        UpdateTelegramSettingsRequest $request,
        SyncTelegramNotificationChannels $syncTelegramChannels,
    ): RedirectResponse|SymfonyResponse {
        $user = $request->user();
        $notificationsEnabled = $request->boolean('telegram_notifications_enabled');

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

    private function saveTelegramSettings(User $user, bool $notificationsEnabled): void
    {
        $user->forceFill([
            'telegram_notifications_enabled' => $notificationsEnabled,
            'telegram_connection_token' => $user->telegram_connection_token ?: $this->newTelegramConnectionToken(),
        ])->save();
    }

    private function telegramBotUsername(): ?string
    {
        $username = trim((string) config('services.telegram.bot_username', ''));

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

    private function newTelegramConnectionToken(): string
    {
        do {
            $token = Str::random(48);
        } while (User::query()->where('telegram_connection_token', $token)->exists());

        return $token;
    }
}
