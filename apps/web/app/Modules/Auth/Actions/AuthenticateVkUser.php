<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTO\VkUserData;
use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Billing\Application\Services\AssignFreeSubscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Actions\CreateOrganizationForUser;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuthenticateVkUser
{
    public function __construct(
        private CreateOrganizationForUser $createOrganizationForUser,
        private CreateDefaultFolderForOrganization $createDefaultFolderForOrganization,
        private AssignFreeSubscription $assignFreeSubscription,
    ) {}

    public function handle(VkUserData $data): User
    {
        $wasCreated = false;

        $user = DB::transaction(function () use ($data, &$wasCreated): User {
            $user = User::query()
                ->where('vk_id', $data->id)
                ->first();

            if (! $user) {
                $user = User::query()
                    ->where('email', $data->email)
                    ->first();

                if ($user && $user->vk_id !== null && $user->vk_id !== $data->id) {
                    throw ValidationException::withMessages([
                        'vk' => 'Этот email уже привязан к другому аккаунту VK.',
                    ]);
                }
            }

            if (! $user) {
                $wasCreated = true;

                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(64)),
                    'vk_id' => $data->id,
                ]);
            } else {
                $user->forceFill([
                    'name' => $user->name ?: $data->name,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'vk_id' => $user->vk_id ?: $data->id,
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

        if ($wasCreated) {
            Mail::to($user->email)->send(new RegistrationCompletedMail($user->name));
        }

        return $user;
    }
}
