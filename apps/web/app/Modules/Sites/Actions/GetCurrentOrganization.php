<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use RuntimeException;

final class GetCurrentOrganization
{
    public function handle(User $user): Organization
    {
        $organization = $user->organizations()->first();

        if (! $organization instanceof Organization) {
            throw new RuntimeException('User does not belong to any organization.');
        }

        return $organization;
    }
}
