<?php

namespace app\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'invited_at',
        'joined_at',
    ];
}
