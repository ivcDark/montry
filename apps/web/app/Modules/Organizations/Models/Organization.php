<?php

namespace App\Modules\Organizations\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization as IdentityOrganization;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property integer $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property OrganizationStatus $status Статус организации
 * @method static Builder|Organization whereName($value)
 * @property-read User user Связанный пользователь
 */
class Organization extends IdentityOrganization
{
    public function siteFolders(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function defaultSiteFolder(): HasOne
    {
        return $this->hasOne(Project::class)->where('is_default', true);
    }
}
