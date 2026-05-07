<?php

namespace App\Modules\Checks\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'type',
        'is_enabled',
        'timeout_ms',
        'settings',
    ];
}
