<?php

namespace App\Application\Onboarding\Actions;

use App\Modules\Billing\Application\Services\AssignFreeSubscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Actions\CreateOrganizationForUser;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use Illuminate\Support\Facades\DB;

final readonly class CompleteAccountRegistration
{
    public function __construct(
        private CreateOrganizationForUser $createOrganizationForUser,
        private CreateDefaultFolderForOrganization $createDefaultFolderForOrganization,
        private AssignFreeSubscription $assignFreeSubscription,
    ) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $organization = $this->createOrganizationForUser->handle($user);
            $this->createDefaultFolderForOrganization->handle($organization);
            $this->assignFreeSubscription->handle($organization->id);
        });
    }
}
