<?php

namespace App\Modules\Sites\Models;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class Site extends MonitoredResource
{
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'project_id');
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(SiteMonitor::class, 'monitored_resource_id');
    }
}
