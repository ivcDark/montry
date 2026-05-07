<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\DTO\RegisterUserData;
use App\Modules\Organizations\Actions\CreateOrganizationForUser;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use Illuminate\Support\Facades\DB;

final readonly class CreateAccount
{
    public function __construct(
        private RegisterUser                       $registerUser,
        private CreateOrganizationForUser          $createOrganizationForUser,
        private CreateDefaultFolderForOrganization $createDefaultFolderForOrganization,
    )
    {
    }

    public function handle(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data):User {
            $user = $this->registerUser->handle($data);
            $organization = $this->createOrganizationForUser->handle($user);
            $this->createDefaultFolderForOrganization->handle($organization);

            return $user;
        });
    }
}
