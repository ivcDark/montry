<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'yandex_id',
        'vk_id',
        'is_admin',
        'is_blocked',
        'telegram_notifications_enabled',
        'telegram_connection_token',
        'telegram_chat_id',
        'telegram_username',
        'telegram_connected_at',
        'max_notifications_enabled',
        'max_connection_token',
        'max_chat_id',
        'max_username',
        'max_connected_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'telegram_notifications_enabled' => 'boolean',
            'telegram_connected_at' => 'datetime',
            'max_notifications_enabled' => 'boolean',
            'max_connected_at' => 'datetime',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('role')
            ->withPivot('status')
            ->withPivot('invited_at')
            ->withPivot('joined_at')
            ->withTimestamps();
    }
}

