<?php

namespace App\Modules\Organizations\Models;

use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationPlan;
use App\Modules\Organizations\Enums\OrganizationStatus;
use App\Modules\Sites\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property OrganizationPlan $plan План подписки на сервис
 * @property OrganizationStatus $status Статус организации
 * @method static Builder|Organization whereName($value)
 * @property-read User user Связанный пользователь
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'plan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'plan' => OrganizationPlan::class,
            'status' => OrganizationStatus::class,
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withPivot('invited_at')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function siteFolders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function defaultSiteFolder(): HasOne
    {
        return $this->hasOne(Folder::class)->where('is_default', true);
    }
}
