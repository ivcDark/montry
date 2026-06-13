<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Organizations\Enums\OrganizationStatus;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class CreateOrganizationForUser
{
    public function handle(User $user): Organization
    {
        $name = $this->organizationNameFor($user);

        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'timezone' => '+3',
            'status' => OrganizationStatus::Active->value,
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'invited_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'joined_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        return $organization;
    }

    private function organizationNameFor(User $user): string
    {
        return $user->name ?? '';
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'organization';
        }

        $slug = $baseSlug;
        $counter = 2;
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
