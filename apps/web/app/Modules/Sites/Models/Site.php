<?php

namespace App\Modules\Sites\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;

/**
 * @property integer $id
 * @property integer $organization_it
 * @property integer $folder_id
 * @property integer $created_user_id
 * @property string $name
 * @property string $url
 * @property string $scheme
 * @property string $host
 * @property integer $port
 * @property string $path
 * @property SiteStatus $status
 * @property string $notes
 * @method static Builder|Organization whereName($value)
 * @property-read Organization organization Организация
 * @property-read Folder folder Папка/проект
 * @property-read SiteMonitor monitors Типы проверок сайта
 */
class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'folder_id',
        'created_user_id',
        'name',
        'url',
        'scheme',
        'host',
        'port',
        'path',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(SiteMonitor::class, 'site_id');
    }
}
