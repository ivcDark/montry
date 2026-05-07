<?php

namespace App\Modules\Sites\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'color',
        'sort_order',
    ];
}
