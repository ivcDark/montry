<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withPivot('status')
            ->withPivot('invited_at')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function defaultProject(): HasOne
    {
        return $this->hasOne(Project::class)->where('is_default', true);
    }
}
