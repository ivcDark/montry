<?php

namespace app\Modules\Sites\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'folder_id',
        'created_user_id',
        'name',
        'primary_domain',
        'status',
        'notes',
    ];
}
