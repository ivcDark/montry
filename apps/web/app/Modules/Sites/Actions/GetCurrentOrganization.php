<?php

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
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
