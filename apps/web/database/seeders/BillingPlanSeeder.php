<?php

namespace Database\Seeders;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use Illuminate\Database\Seeder;

final class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $allMonitorTypes = app(MonitorTypeCatalog::class)->allCodes();

        $plans = [
            'free' => [
                'name' => 'Free',
                'description' => 'Базовый мониторинг для первого сайта.',
                'price_cents' => 0,
                'sort_order' => 10,
                'limits' => [
                    'max_sites' => ['limit' => 1],
                    'max_monitors' => ['limit' => null],
                    'allowed_monitor_types' => ['types' => $allMonitorTypes],
                    'history_retention_days' => ['days' => 3],
                    'minimum_check_interval_seconds' => ['seconds' => 900],
                    'notification_channels' => ['channels' => ['email']],
                    'can_create_projects' => ['enabled' => false],
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'Мониторинг до 10 сайтов с Telegram-уведомлениями и историей 30 дней.',
                'price_cents' => 39000,
                'sort_order' => 20,
                'limits' => [
                    'max_sites' => ['limit' => 10],
                    'max_monitors' => ['limit' => null],
                    'allowed_monitor_types' => ['types' => $allMonitorTypes],
                    'history_retention_days' => ['days' => 30],
                    'minimum_check_interval_seconds' => ['seconds' => 300],
                    'notification_channels' => ['channels' => ['email', 'telegram']],
                    'can_create_projects' => ['enabled' => false],
                ],
            ],
            'plus' => [
                'name' => 'Plus',
                'description' => 'Расширенный мониторинг до 30 сайтов с проверками от 3 минут.',
                'price_cents' => 69000,
                'sort_order' => 30,
                'limits' => [
                    'max_sites' => ['limit' => 30],
                    'max_monitors' => ['limit' => null],
                    'allowed_monitor_types' => ['types' => $allMonitorTypes],
                    'history_retention_days' => ['days' => 60],
                    'minimum_check_interval_seconds' => ['seconds' => 180],
                    'notification_channels' => ['channels' => ['email', 'telegram']],
                    'can_create_projects' => ['enabled' => true],
                ],
            ],
        ];

        foreach ($plans as $code => $data) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price_cents' => $data['price_cents'],
                    'currency' => 'RUB',
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ],
            );

            foreach ($data['limits'] as $key => $value) {
                $plan->limits()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );
            }
        }
    }
}
