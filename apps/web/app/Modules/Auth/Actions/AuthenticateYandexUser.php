<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTO\YandexUserData;
use App\Modules\Billing\Application\Services\AssignFreeSubscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Actions\CreateOrganizationForUser;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuthenticateYandexUser
{
    public function __construct(
        private CreateOrganizationForUser $createOrganizationForUser,
        private CreateDefaultFolderForOrganization $createDefaultFolderForOrganization,
        private AssignFreeSubscription $assignFreeSubscription,
    ) {}

    public function handle(YandexUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()
                ->where('yandex_id', $data->id)
                ->first();

            if (! $user) {
                $user = User::query()
                    ->where('email', $data->email)
                    ->first();

                if ($user && $user->yandex_id !== null && $user->yandex_id !== $data->id) {
                    throw ValidationException::withMessages([
                        'yandex' => 'Этот email уже привязан к другому аккаунту Яндекса.',
                    ]);
                }
            }

            if (! $user) {
                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(64)),
                    'yandex_id' => $data->id,
                ]);
            } else {
                $user->forceFill([
                    'name' => $user->name ?: $data->name,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'yandex_id' => $user->yandex_id ?: $data->id,
                ])->save();
            }

            if ((bool) $user->is_blocked) {
                return $user->refresh();
            }

            if (! $user->organizations()->exists()) {
                $organization = $this->createOrganizationForUser->handle($user);
                $this->createDefaultFolderForOrganization->handle($organization);
                $this->assignFreeSubscription->handle($organization->id);
            }

            return $user->refresh();
        });
    }
}
