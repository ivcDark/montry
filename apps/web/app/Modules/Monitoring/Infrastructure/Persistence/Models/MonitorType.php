<?php

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class MonitorType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'short_label',
        'description',
        'category',
        'is_active',
        'is_default_for_site',
        'default_enabled',
        'is_paid',
        'unit_price_cents',
        'currency',
        'unit_label',
        'sort_order',
        'default_interval_seconds',
        'default_timeout_ms',
        'default_settings',
        'default_expected',
        'ui_meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default_for_site' => 'boolean',
            'default_enabled' => 'boolean',
            'is_paid' => 'boolean',
            'unit_price_cents' => 'integer',
            'sort_order' => 'integer',
            'default_interval_seconds' => 'integer',
            'default_timeout_ms' => 'integer',
            'default_settings' => 'array',
            'default_expected' => 'array',
            'ui_meta' => 'array',
        ];
    }
}
