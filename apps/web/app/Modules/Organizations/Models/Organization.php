<?php

namespace App\Modules\Organizations\Models;

use App\Models\User;
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
 * @property string $plan План подписки на сервис
 * @property string $status Статус организации
 * @method static Builder|Organization whereName($value)
 * @property-read User user Связанный пользователь
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    public const PLAN_FREE = 'free';
    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'plan',
        'status',
    ];

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
