<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Identity\Presentation\Http\Requests\UpdateProfileSettingsRequest;
use App\Modules\Identity\Presentation\Http\Requests\UpdateTelegramSettingsRequest;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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

    public function updateTelegram(UpdateTelegramSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'telegram_notifications_enabled' => $request->boolean('telegram_notifications_enabled'),
            'telegram_connection_token' => $user->telegram_connection_token ?: $this->newTelegramConnectionToken(),
        ])->save();

        return redirect()
            ->route('settings.index')
            ->with('success', 'Настройки Telegram сохранены.');
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

        return "https://t.me/{$botUsername}?start={$user->telegram_connection_token}";
    }

    private function newTelegramConnectionToken(): string
    {
        do {
            $token = Str::random(48);
        } while (User::query()->where('telegram_connection_token', $token)->exists());

        return $token;
    }
}
