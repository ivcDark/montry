<?php

namespace App\Modules\Sites\Models;

use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Project
{
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'project_id');
    }
}
